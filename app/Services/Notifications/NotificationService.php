<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;

/**
 * Outbox-style notifications. Calls from request handlers only INSERT rows
 * (deduplicated by a unique key); the scheduler's `notifications:send` command
 * delivers them with retries and backoff. Nothing here blocks the request on
 * a provider.
 */
class NotificationService
{
    public const EVENTS = [
        'booking_created' => 'Booking created',
        'payment_confirmed' => 'Payment confirmed',
        'payment_failed' => 'Payment failed',
        'pickup_scheduled' => 'Pickup scheduled',
        'rider_assigned' => 'Rider assigned',
        'picked_up' => 'Parcel picked up',
        'in_transit' => 'Parcel in transit',
        'out_for_delivery' => 'Out for delivery',
        'delivery_failed' => 'Delivery attempted / failed',
        'delivered' => 'Parcel delivered',
        'return_initiated' => 'Return initiated',
        'return_completed' => 'Return completed',
        'cancelled' => 'Shipment cancelled',
        'support_updated' => 'Support case updated',
        'delivery_code' => 'Recipient delivery code',
        'rider_assignment' => 'New assignment (to rider)',
    ];

    /** Events that are also sent to the recipient (not just the sender/owner). */
    private const RECIPIENT_EVENTS = ['out_for_delivery', 'delivered', 'delivery_failed'];

    /** Retry backoff in minutes after attempt N. */
    public const BACKOFF = [1, 5, 15, 60];

    public const MAX_ATTEMPTS = 5;

    public function __construct(private EmailSender $email, private SmsSender $sms) {}

    public function forShipment(Shipment $shipment, string $event, ?ShipmentEvent $timelineEvent = null): void
    {
        $vars = $this->shipmentVars($shipment, $timelineEvent);
        $suffix = $timelineEvent?->id ?? 'initial';
        $owner = $shipment->user;

        $targets = [];
        // Sender / account owner
        $email = $owner?->email ?? $shipment->sender_email;
        if ($email && (! $owner || $owner->wantsNotification('email'))) {
            $targets[] = ['email', $email, $owner?->id];
        }
        $smsOk = $owner ? $owner->wantsNotification('sms') : true;
        if ($smsOk) {
            $targets[] = ['sms', $shipment->sender_phone, $owner?->id];
        }
        if (in_array($event, self::RECIPIENT_EVENTS, true)) {
            if ($shipment->recipient_email) {
                $targets[] = ['email', $shipment->recipient_email, null];
            }
            $targets[] = ['sms', $shipment->recipient_phone, null];
        }

        foreach ($targets as [$channel, $to, $userId]) {
            $this->queue($event, $channel, $to, $vars, "{$event}:shipment:{$shipment->id}:{$suffix}", $userId, $shipment->id);
        }
    }

    public function deliveryCode(Shipment $shipment, string $code): void
    {
        $vars = $this->shipmentVars($shipment) + ['delivery_code' => $code];
        $this->queue('delivery_code', 'sms', $shipment->recipient_phone, $vars, "delivery_code:{$shipment->id}", null, $shipment->id);
        if ($shipment->recipient_email) {
            $this->queue('delivery_code', 'email', $shipment->recipient_email, $vars, "delivery_code:{$shipment->id}", null, $shipment->id);
        }
    }

    public function forPayment(Shipment $shipment, string $event, string $paymentRef): void
    {
        $vars = $this->shipmentVars($shipment) + ['payment_reference' => $paymentRef];
        $owner = $shipment->user;
        $email = $owner?->email ?? $shipment->sender_email;
        if ($email) {
            $this->queue($event, 'email', $email, $vars, "{$event}:{$paymentRef}:{$shipment->id}", $owner?->id, $shipment->id);
        }
    }

    public function toRider(User $rider, Shipment $shipment, int $assignmentId): void
    {
        $vars = $this->shipmentVars($shipment) + ['rider_name' => $rider->name, 'portal_url' => route('rider.dashboard')];
        $this->queue('rider_assignment', 'email', $rider->email, $vars, "rider_assignment:{$assignmentId}:email", $rider->id, $shipment->id);
        if ($rider->phone) {
            $this->queue('rider_assignment', 'sms', $rider->phone, $vars, "rider_assignment:{$assignmentId}:sms", $rider->id, $shipment->id);
        }
    }

    public function forTicket(SupportTicket $ticket, int $messageId): void
    {
        $vars = [
            'business_name' => Settings::get('business_name'),
            'ticket_reference' => $ticket->reference,
            'ticket_subject' => $ticket->subject,
            'ticket_status' => SupportTicket::STATUSES[$ticket->status] ?? $ticket->status,
            'customer_name' => $ticket->contact_name,
            'ticket_url' => $ticket->user_id ? route('account.support.show', $ticket) : route('support.contact'),
        ];
        $this->queue('support_updated', 'email', $ticket->contact_email, $vars, "support_updated:{$ticket->id}:{$messageId}", $ticket->user_id, $ticket->shipment_id);
    }

    /** Render a template and insert a queued log row; duplicates are silently ignored. */
    public function queue(string $event, string $channel, ?string $to, array $vars, string $dedupe, ?int $userId, ?int $shipmentId): void
    {
        if (! $to) {
            return;
        }
        $enabled = $channel === 'sms' ? Settings::get('sms_enabled') : Settings::get('email_enabled');
        $template = NotificationTemplate::where('event', $event)->where('channel', $channel)->where('active', true)->first();
        if (! $template) {
            return;
        }

        $key = $dedupe.':'.$channel.':'.substr(hash('sha256', mb_strtolower($to)), 0, 16);
        NotificationLog::insertOrIgnore([[
            'user_id' => $userId,
            'shipment_id' => $shipmentId,
            'event' => $event,
            'channel' => $channel,
            'recipient' => $to,
            'subject' => $template->subject ? self::render($template->subject, $vars) : null,
            'body' => self::render($template->body, $vars),
            'status' => $enabled ? 'queued' : 'skipped',
            'last_error' => $enabled ? null : ucfirst($channel).' notifications are disabled in settings.',
            'attempts' => 0,
            'next_attempt_at' => now(),
            'dedupe_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    /** Deliver due notifications. Called by the scheduler; safe to run concurrently. */
    public function processDue(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'retrying' => 0];
        $ids = NotificationLog::where('status', 'queued')->where('next_attempt_at', '<=', now())
            ->orderBy('id')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            // Claim atomically so two cron runs never send the same message.
            $claimed = NotificationLog::where('id', $id)->where('status', 'queued')->update(['status' => 'sending', 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $log = NotificationLog::find($id);
            try {
                $result = $log->channel === 'sms'
                    ? $this->sms->send($log->recipient, $log->body)
                    : $this->email->send($log->recipient, (string) $log->subject, $log->body);
                $log->forceFill([
                    'status' => 'sent', 'sent_at' => now(), 'attempts' => $log->attempts + 1,
                    'provider' => $result['provider'], 'provider_message_id' => $result['id'] ?? null, 'last_error' => null,
                ])->save();
                $stats['sent']++;
            } catch (\Throwable $e) {
                $attempts = $log->attempts + 1;
                $final = $attempts >= self::MAX_ATTEMPTS || $e instanceof PermanentDeliveryFailure;
                $log->forceFill([
                    'status' => $final ? 'failed' : 'queued',
                    'attempts' => $attempts,
                    'last_error' => mb_substr($e->getMessage(), 0, 480),
                    'next_attempt_at' => now()->addMinutes(self::BACKOFF[min($attempts - 1, count(self::BACKOFF) - 1)]),
                ])->save();
                Log::warning('Notification delivery failed', ['id' => $log->id, 'channel' => $log->channel, 'attempt' => $attempts, 'error' => $e->getMessage()]);
                $final ? $stats['failed']++ : $stats['retrying']++;
            }
        }

        return $stats;
    }

    public static function render(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $template);
    }

    /** Only customer-safe fields. Never internal notes, passwords or payment data. */
    public function shipmentVars(Shipment $shipment, ?ShipmentEvent $event = null): array
    {
        return [
            'business_name' => Settings::get('business_name'),
            'support_email' => Settings::get('support_email'),
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->publicTrackingUrl(),
            'sender_name' => $shipment->sender_name,
            'recipient_name' => $shipment->recipient_name,
            'status' => $shipment->status->label(),
            'status_description' => $event?->public_description ?? $shipment->status->publicDescription(),
            'destination_city' => $shipment->delivery_city,
            'origin_city' => $shipment->pickup_city,
            'total' => Money::format($shipment->total, $shipment->currency),
            'estimated_delivery' => $shipment->estimated_delivery_to?->format('D j M Y') ?? 'to be confirmed',
        ];
    }
}
