<?php

namespace App\Services\Payments;

use App\Enums\ShipmentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\ShipmentWorkflow;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private ShipmentWorkflow $workflow, private NotificationService $notifications) {}

    /**
     * Start (or resume) online payment for one or more pending shipments.
     * Reuses an open payment for exactly the same shipments so double clicks or
     * refreshes never create a second charge.
     *
     * @param  Collection<int, Shipment>  $shipments
     */
    public function start(Collection $shipments, string $payerEmail, ?User $user): Payment
    {
        if ($shipments->isEmpty()) {
            throw ValidationException::withMessages(['payment' => 'Nothing to pay for.']);
        }
        foreach ($shipments as $s) {
            if (! $s->isPayable()) {
                throw ValidationException::withMessages(['payment' => "Shipment {$s->tracking_number} is not awaiting online payment."]);
            }
        }
        if ($shipments->pluck('currency')->unique()->count() > 1) {
            throw ValidationException::withMessages(['payment' => 'Shipments in different currencies must be paid separately.']);
        }

        return DB::transaction(function () use ($shipments, $payerEmail, $user) {
            $ids = $shipments->pluck('id')->sort()->values();
            Shipment::whereIn('id', $ids)->lockForUpdate()->get();

            // Block a second charge if any of these shipments is already paid.
            $paid = DB::table('payment_shipment')->join('payments', 'payments.id', '=', 'payment_shipment.payment_id')
                ->whereIn('payment_shipment.shipment_id', $ids)->where('payments.status', 'successful')->exists();
            if ($paid) {
                throw ValidationException::withMessages(['payment' => 'Payment has already been received for this shipment.']);
            }

            $open = Payment::where('status', 'pending')->where('expires_at', '>', now())
                ->whereHas('shipments', fn ($q) => $q->whereIn('shipments.id', $ids))
                ->with('shipments:id')->get()
                ->first(fn (Payment $p) => $p->shipments->pluck('id')->sort()->values()->all() === $ids->all());
            if ($open && $open->checkout_url) {
                return $open;
            }

            $gateway = GatewayFactory::make();
            $totalMinor = $shipments->sum(fn ($s) => Money::toMinor($s->total));
            $first = $shipments->first();
            $payment = Payment::create([
                'user_id' => $user?->id ?? $first->user_id,
                'business_id' => $first->business_id,
                'provider' => $gateway->name(),
                'reference' => 'PAY-'.strtoupper(Str::random(20)),
                'amount' => Money::fromMinor($totalMinor),
                'currency' => $first->currency,
                'status' => 'pending',
                'payer_email' => $payerEmail,
                'expires_at' => now()->addMinutes((int) config('courier.payments.pending_ttl_minutes', 60)),
            ]);
            foreach ($shipments as $s) {
                $payment->shipments()->attach($s->id, ['amount' => $s->total]);
            }
            $payment->checkout_url = $gateway->initialize($payment, route('payments.callback'));
            $payment->save();

            return $payment;
        });
    }

    /** Online payment of an issued business invoice. */
    public function startForInvoice(Invoice $invoice, string $payerEmail, User $user): Payment
    {
        if ($invoice->status !== 'issued') {
            throw ValidationException::withMessages(['payment' => 'This invoice is not awaiting payment.']);
        }

        return DB::transaction(function () use ($invoice, $payerEmail, $user) {
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            $open = Payment::where('invoice_id', $invoice->id)->where('status', 'pending')->where('expires_at', '>', now())->first();
            if ($open && $open->checkout_url) {
                return $open;
            }
            $gateway = GatewayFactory::make();
            $payment = Payment::create([
                'user_id' => $user->id,
                'business_id' => $invoice->business_id,
                'invoice_id' => $invoice->id,
                'provider' => $gateway->name(),
                'reference' => 'INVPAY-'.strtoupper(Str::random(18)),
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
                'status' => 'pending',
                'payer_email' => $payerEmail,
                'expires_at' => now()->addMinutes((int) config('courier.payments.pending_ttl_minutes', 60)),
            ]);
            $payment->checkout_url = $gateway->initialize($payment, route('payments.callback'));
            $payment->save();

            return $payment;
        });
    }

    /**
     * Verify with the provider and settle. Idempotent: safe to call from the
     * browser redirect, the webhook and the reconciliation job concurrently.
     *
     * @return string outcome: successful | failed | cancelled | pending | already_final | mismatch
     */
    public function confirm(string $reference, string $source): string
    {
        $payment = Payment::where('reference', $reference)->first();
        if (! $payment) {
            return 'unknown';
        }
        if ($payment->isFinal()) {
            return 'already_final';
        }

        $gateway = GatewayFactory::make($payment->provider);
        $result = $gateway->verify($payment);

        $outcome = DB::transaction(function () use ($reference, $result, $source) {
            /** @var Payment $p */
            $p = Payment::where('reference', $reference)->lockForUpdate()->first();
            if ($p->isFinal()) {
                return 'already_final';
            }

            if ($result['status'] === 'success') {
                $mismatch = [];
                if ($result['reference'] !== $p->reference) {
                    $mismatch[] = 'reference';
                }
                if ($result['amount_minor'] !== Money::toMinor($p->amount)) {
                    $mismatch[] = 'amount';
                }
                if (strtoupper((string) $result['currency']) !== strtoupper($p->currency)) {
                    $mismatch[] = 'currency';
                }
                if ($mismatch) {
                    $p->forceFill(['status' => 'failed', 'failure_reason' => 'Verification mismatch: '.implode(', ', $mismatch)])->save();
                    Audit::log('payment.verification_mismatch', $p, ['fields' => $mismatch, 'source' => $source], null);
                    Log::alert('Payment verification mismatch', ['reference' => $p->reference, 'fields' => $mismatch]);

                    return 'mismatch';
                }
                $p->forceFill([
                    'status' => 'successful',
                    'provider_transaction_id' => $result['transaction_id'],
                    'channel' => $result['channel'],
                    'paid_at' => $result['paid_at'] ? Carbon::parse($result['paid_at']) : now(),
                    'verified_at' => now(),
                    'metadata' => array_merge($p->metadata ?? [], ['verified_via' => $source]),
                ])->save();
                if ($p->invoice_id) {
                    Invoice::whereKey($p->invoice_id)->update(['status' => 'paid', 'paid_at' => now()]);
                }

                return 'successful';
            }

            if (in_array($result['status'], ['failed', 'abandoned'], true)) {
                $p->forceFill([
                    'status' => $result['status'] === 'failed' ? 'failed' : 'cancelled',
                    'failure_reason' => $result['message'] ?? ucfirst($result['status']),
                    'verified_at' => now(),
                ])->save();

                return $p->status;
            }

            return 'pending';
        });

        $payment->refresh();
        if ($outcome === 'successful') {
            foreach ($payment->shipments as $shipment) {
                if ($shipment->status === ShipmentStatus::PendingPayment) {
                    $this->workflow->transition($shipment, ShipmentStatus::Booked, null, [
                        'role' => 'system', 'source' => 'system', 'public_description' => 'Payment received. Shipment confirmed and booked.',
                    ]);
                }
                $this->notifications->forPayment($shipment, 'payment_confirmed', $payment->reference);
            }
        } elseif (in_array($outcome, ['failed', 'mismatch'], true)) {
            foreach ($payment->shipments as $shipment) {
                $this->notifications->forPayment($shipment, 'payment_failed', $payment->reference);
            }
        }

        return $outcome;
    }

    /** @return array{0:int, 1:string} HTTP status + outcome */
    public function handleWebhook(string $provider, string $rawBody, array $headers): array
    {
        try {
            $gateway = GatewayFactory::make($provider);
        } catch (\Throwable) {
            return [404, 'unknown_provider'];
        }
        if (! $gateway->verifyWebhook($rawBody, $headers)) {
            Log::warning('Rejected webhook with invalid signature', ['provider' => $provider]);

            return [401, 'invalid_signature'];
        }

        $event = $gateway->parseWebhook($rawBody);
        $eventType = $event['type'];
        $reference = (string) ($event['reference'] ?? '');
        $key = $event['key'];

        try {
            $record = PaymentWebhookEvent::create(['provider' => $provider, 'event_key' => $key, 'event_type' => $eventType, 'reference' => $reference ?: null]);
        } catch (UniqueConstraintViolationException) {
            return [200, 'duplicate'];
        }

        $outcome = 'ignored';
        if ($reference && $event['settle']) {
            // Never trust the webhook body alone: re-verify with the provider API.
            $outcome = $this->confirm($reference, 'webhook');
        } elseif ($reference && str_starts_with($eventType, 'refund.')) {
            $outcome = 'refund_event_logged';
        }
        $record->forceFill(['outcome' => $outcome, 'processed_at' => now()])->save();

        return [200, $outcome];
    }

    /** Reconcile abandoned/interrupted checkouts. Run by the scheduler. */
    public function reconcileStale(): int
    {
        $count = 0;
        Payment::where('status', 'pending')->where('expires_at', '<', now())->limit(100)->get()
            ->each(function (Payment $p) use (&$count) {
                try {
                    $outcome = $this->confirm($p->reference, 'reconciliation');
                    if ($outcome === 'pending') {
                        $p->forceFill(['status' => 'cancelled', 'failure_reason' => 'Checkout expired without payment.'])->save();
                    }
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('Payment reconciliation failed', ['reference' => $p->reference, 'error' => $e->getMessage()]);
                }
            });

        return $count;
    }

    public function refund(Payment $payment, string $amount, string $reason, User $actor): Refund
    {
        if (! $actor->isRole('admin')) {
            throw new AuthorizationException('Only administrators can issue refunds.');
        }
        $amountMinor = Money::toMinor($amount);
        $refunded = $payment->refunds()->whereIn('status', ['pending', 'processed'])->get()->sum(fn ($r) => Money::toMinor($r->amount));
        $available = Money::toMinor($payment->amount) - $refunded;
        if ($payment->status !== 'successful' || $amountMinor <= 0 || $amountMinor > $available) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be positive and no more than '.Money::format(Money::fromMinor($available), $payment->currency).'.']);
        }

        $result = GatewayFactory::make($payment->provider)->refund($payment, $amountMinor, $reason);
        $refund = $payment->refunds()->create([
            'amount' => Money::fromMinor($amountMinor),
            'reason' => $reason,
            'provider_reference' => $result['reference'],
            'status' => $result['status'],
            'requested_by' => $actor->id,
            'processed_at' => $result['status'] === 'processed' ? now() : null,
            'failure_reason' => $result['message'] && $result['status'] === 'failed' ? $result['message'] : null,
        ]);
        if ($result['status'] !== 'failed' && $amountMinor === $available) {
            $payment->forceFill(['status' => 'refunded'])->save();
        }
        Audit::log('payment.refund', $payment, ['amount' => $refund->amount, 'status' => $refund->status, 'reason' => $reason]);

        return $refund;
    }
}
