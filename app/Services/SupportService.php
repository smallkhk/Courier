<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Audit;
use Illuminate\Support\Str;

class SupportService
{
    public function __construct(private NotificationService $notifications) {}

    /** @param array{contact_name:string, contact_email:string, contact_phone?:?string, category:string, subject:string, message:string, tracking_number?:?string} $data */
    public function open(array $data, ?User $user): SupportTicket
    {
        $shipmentId = null;
        if (! empty($data['tracking_number'])) {
            $q = Shipment::where('tracking_number', $data['tracking_number']);
            // Only link a shipment the requester can see (guests: link but grant no access).
            $shipmentId = $user ? Shipment::visibleTo($user)->where('tracking_number', $data['tracking_number'])->value('id') : $q->value('id');
        }
        do {
            $ref = 'SR-'.strtoupper(Str::random(8));
        } while (SupportTicket::where('reference', $ref)->exists());

        $ticket = SupportTicket::create([
            'reference' => $ref,
            'user_id' => $user?->id,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'shipment_id' => $shipmentId,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'priority' => in_array($data['category'], ['missing', 'damaged', 'claim'], true) ? 'high' : 'normal',
        ]);
        $msg = $ticket->messages()->create(['user_id' => $user?->id, 'body' => $data['message']]);
        $this->notifications->forTicket($ticket, $msg->id);

        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $author, string $body, bool $internal = false, ?string $status = null): SupportMessage
    {
        $internal = $internal && $author->isStaff();
        $msg = $ticket->messages()->create(['user_id' => $author->id, 'body' => $body, 'is_internal' => $internal]);

        if ($author->isStaff()) {
            if ($status && array_key_exists($status, SupportTicket::STATUSES) && $status !== $ticket->status) {
                Audit::log('support.status_changed', $ticket, ['from' => $ticket->status, 'to' => $status]);
                $ticket->status = $status;
            } elseif (! $internal && $ticket->status === 'open') {
                $ticket->status = 'in_progress';
            }
            $ticket->save();
            if (! $internal) {
                $this->notifications->forTicket($ticket, $msg->id);
            }
        } else {
            if (in_array($ticket->status, ['awaiting_customer', 'resolved'], true)) {
                $ticket->update(['status' => 'open']);
            }
        }

        return $msg;
    }
}
