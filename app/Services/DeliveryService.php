<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\CodCollection;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(private ShipmentWorkflow $workflow, private FileStorage $files) {}

    /**
     * @param  array{recipient_name:string, note?:?string, signature?:?string, photo?:?UploadedFile, photo_consent?:bool, delivery_code?:?string, cod_amount_collected?:?string}  $data
     */
    public function submitProof(Shipment $shipment, User $actor, array $data): DeliveryProof
    {
        $role = ShipmentWorkflow::roleFor($actor);
        if ($role === 'rider') {
            $this->workflow->assertRiderAssigned($shipment, $actor);
        } elseif (! in_array($role, ['admin', 'dispatcher'], true)) {
            throw new AuthorizationException;
        }
        if (! $shipment->status->canTransitionTo(ShipmentStatus::Delivered, $role)) {
            throw ValidationException::withMessages(['status' => 'This shipment is not out for delivery.']);
        }

        $errors = [];
        if (Settings::get('proof_require_signature') && empty($data['signature'])) {
            $errors['signature'] = 'A recipient signature is required.';
        }
        if (Settings::get('proof_require_photo') && empty($data['photo'])) {
            $errors['photo'] = 'A delivery photo is required.';
        }
        if (! empty($data['photo']) && empty($data['photo_consent'])) {
            $errors['photo_consent'] = 'Confirm the recipient agreed to the photo.';
        }
        $codeOk = false;
        if ($shipment->delivery_code_hash) {
            $codeOk = ! empty($data['delivery_code']) && Hash::check((string) $data['delivery_code'], $shipment->delivery_code_hash);
            if (Settings::get('proof_require_code') && ! $codeOk) {
                $errors['delivery_code'] = 'The delivery code is incorrect.';
            }
        }
        $cod = $shipment->payment_method === 'cod' ? CodCollection::where('shipment_id', $shipment->id)->first() : null;
        if ($cod && ($data['cod_amount_collected'] ?? '') === '') {
            $errors['cod_amount_collected'] = 'Enter the cash amount collected.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $signaturePath = ! empty($data['signature']) ? $this->files->storeSignature($data['signature'], 'proofs/signatures') : null;
        $photoPath = ! empty($data['photo']) ? $this->files->storeImage($data['photo'], 'proofs/photos') : null;

        $proof = DB::transaction(function () use ($shipment, $actor, $data, $signaturePath, $photoPath, $codeOk, $cod) {
            $proof = DeliveryProof::create([
                'shipment_id' => $shipment->id,
                'submitted_by' => $actor->id,
                'recipient_name' => $data['recipient_name'],
                'delivered_at' => now(),
                'signature_path' => $signaturePath,
                'photo_path' => $photoPath,
                'photo_consent' => (bool) ($data['photo_consent'] ?? false),
                'code_verified' => $codeOk,
                'note' => $data['note'] ?? null,
            ]);
            if ($cod) {
                $collected = (string) $data['cod_amount_collected'];
                $cod->forceFill([
                    'rider_id' => $actor->id,
                    'amount_collected' => $collected,
                    'collected_at' => now(),
                    'status' => Money::toMinor($collected) === Money::toMinor($cod->amount_due) ? 'collected' : 'discrepancy',
                ])->save();
            }

            return $proof;
        });

        $this->workflow->transition($shipment, ShipmentStatus::Delivered, $actor, [
            'proof' => $proof,
            'location' => $shipment->delivery_city,
            'public_description' => 'Delivered. Received by '.self::initials($data['recipient_name']),
            'internal_note' => $data['note'] ?? null,
        ]);
        Audit::log('delivery.proof_submitted', $shipment, ['proof_id' => $proof->id, 'signature' => (bool) $signaturePath, 'photo' => (bool) $photoPath, 'code_verified' => $codeOk]);

        return $proof;
    }

    /**
     * @param  array{reason:string, note?:?string, evidence?:?UploadedFile}  $data
     */
    public function recordFailedAttempt(Shipment $shipment, User $actor, array $data): DeliveryAttempt
    {
        if (! array_key_exists($data['reason'], DeliveryAttempt::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }
        $role = ShipmentWorkflow::roleFor($actor);
        if (! $shipment->status->canTransitionTo(ShipmentStatus::DeliveryAttempted, $role)) {
            throw ValidationException::withMessages(['status' => 'A failed attempt can only be recorded while out for delivery.']);
        }
        if ($role === 'rider') {
            $this->workflow->assertRiderAssigned($shipment, $actor);
        }
        $evidence = ! empty($data['evidence']) ? $this->files->storeImage($data['evidence'], 'attempts') : null;

        $attempt = DeliveryAttempt::create([
            'shipment_id' => $shipment->id,
            'rider_id' => $role === 'rider' ? $actor->id : null,
            'attempted_at' => now(),
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
            'evidence_path' => $evidence,
        ]);

        $this->workflow->transition($shipment, ShipmentStatus::DeliveryAttempted, $actor, [
            'location' => $shipment->delivery_city,
            'public_description' => 'Delivery attempted: '.DeliveryAttempt::REASONS[$data['reason']].'. We will contact you about next steps.',
            'internal_note' => $data['note'] ?? null,
        ]);

        return $attempt;
    }

    /** Operations decides what happens after a failed attempt. */
    public function resolveAttempt(DeliveryAttempt $attempt, User $staff, string $resolution, ?string $retryOn, ?string $note): void
    {
        if (! $staff->isStaff()) {
            throw new AuthorizationException;
        }
        if (! array_key_exists($resolution, DeliveryAttempt::RESOLUTIONS)) {
            throw ValidationException::withMessages(['resolution' => 'Choose an action.']);
        }
        $attempt->forceFill(['resolution' => $resolution, 'retry_on' => $retryOn, 'resolved_by' => $staff->id, 'resolved_at' => now()])->save();
        $shipment = $attempt->shipment;

        match ($resolution) {
            'retry' => $this->workflow->transition($shipment, ShipmentStatus::AtDestinationFacility, $staff, [
                'public_description' => 'Another delivery attempt has been scheduled'.($retryOn ? ' for '.Carbon::parse($retryOn)->format('D j M') : '').'.',
                'internal_note' => $note,
            ]),
            'hold' => $this->workflow->transition($shipment, ShipmentStatus::OnHold, $staff, [
                'public_description' => 'Your parcel is being held at our hub. Please contact support to arrange delivery or collection.',
                'internal_note' => $note,
            ]),
            'return' => $this->workflow->transition($shipment, ShipmentStatus::ReturnInitiated, $staff, ['internal_note' => $note]),
            'contact_recipient' => $this->workflow->note($shipment, 'Our team will contact the recipient to arrange delivery.', $staff, $staff->role, $note),
        };
        Audit::log('delivery.attempt_resolved', $shipment, ['attempt_id' => $attempt->id, 'resolution' => $resolution]);
    }

    private static function initials(string $name): string
    {
        // Public timeline shows only initials, never the full recipient name.
        return collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).'.')->implode(' ');
    }
}
