<?php

namespace App\Services\Notifications;

use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS drivers:
 *  - log    development only — writes to the log, sends nothing
 *  - twilio Twilio Programmable Messaging (US and international)
 *  - termii Termii (Africa)
 * Numbers are normalised to E.164 (+12125550123) before sending.
 */
class SmsSender
{
    /** @return array{provider:string, id:?string} */
    public function send(string $to, string $body): array
    {
        $phone = Phone::toE164($to);
        if (! $phone) {
            throw new PermanentDeliveryFailure('Invalid phone number.');
        }

        return match (config('courier.sms.driver')) {
            'twilio' => $this->twilio($phone, $body),
            'termii' => $this->termii($phone, $body),
            default => $this->log($phone, $body),
        };
    }

    private function twilio(string $phone, string $body): array
    {
        $cfg = config('courier.sms.twilio');
        if (! $cfg['sid'] || ! $cfg['token'] || (! $cfg['from'] && ! $cfg['messaging_service_sid'])) {
            throw new PermanentDeliveryFailure('Twilio is not configured (TWILIO_SID / TWILIO_AUTH_TOKEN / TWILIO_FROM or TWILIO_MESSAGING_SERVICE_SID).');
        }
        $params = ['To' => $phone, 'Body' => $body];
        $cfg['messaging_service_sid'] ? $params['MessagingServiceSid'] = $cfg['messaging_service_sid'] : $params['From'] = $cfg['from'];

        $res = Http::timeout(15)->asForm()->withBasicAuth($cfg['sid'], $cfg['token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Messages.json", $params);
        if ($res->status() === 429 || $res->serverError()) {
            throw new \RuntimeException('Twilio temporary failure: HTTP '.$res->status());
        }
        if (! $res->successful()) {
            // 4xx: invalid number, unverified sender, blocked content… retrying won't help.
            throw new PermanentDeliveryFailure('Twilio rejected the message: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }

        return ['provider' => 'twilio', 'id' => (string) $res->json('sid')];
    }

    private function termii(string $phone, string $body): array
    {
        $cfg = config('courier.sms.termii');
        if (! $cfg['api_key'] || ! $cfg['sender_id']) {
            throw new PermanentDeliveryFailure('Termii is not configured (TERMII_API_KEY / TERMII_SENDER_ID).');
        }
        $res = Http::timeout(15)->acceptJson()->post(rtrim($cfg['base_url'], '/').'/api/sms/send', [
            'api_key' => $cfg['api_key'],
            'to' => ltrim($phone, '+'),
            'from' => $cfg['sender_id'],
            'sms' => $body,
            'type' => 'plain',
            'channel' => $cfg['channel'],
        ]);
        if ($res->status() >= 400 && $res->status() < 500) {
            throw new PermanentDeliveryFailure('Termii rejected the message: HTTP '.$res->status());
        }
        if (! $res->successful()) {
            throw new \RuntimeException('Termii temporary failure: HTTP '.$res->status());
        }

        return ['provider' => 'termii', 'id' => (string) ($res->json('message_id') ?? '')];
    }

    private function log(string $phone, string $body): array
    {
        Log::info('[SMS DEV LOG — not sent]', ['to' => $phone, 'body' => $body]);

        return ['provider' => 'log', 'id' => null];
    }
}
