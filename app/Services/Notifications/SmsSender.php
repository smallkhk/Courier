<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS drivers: "log" (development — writes to the log, sends nothing) and "termii".
 */
class SmsSender
{
    /** @return array{provider:string, id:?string} */
    public function send(string $to, string $body): array
    {
        $phone = self::normalizePhone($to);
        if (! $phone) {
            throw new PermanentDeliveryFailure('Invalid phone number.');
        }

        $driver = config('courier.sms.driver');
        if ($driver === 'termii') {
            $cfg = config('courier.sms.termii');
            if (! $cfg['api_key'] || ! $cfg['sender_id']) {
                throw new PermanentDeliveryFailure('Termii is not configured (TERMII_API_KEY / TERMII_SENDER_ID).');
            }
            $res = Http::timeout(15)->acceptJson()->post(rtrim($cfg['base_url'], '/').'/api/sms/send', [
                'api_key' => $cfg['api_key'],
                'to' => $phone,
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

        Log::info('[SMS DEV LOG — not sent]', ['to' => $phone, 'body' => $body]);

        return ['provider' => 'log', 'id' => null];
    }

    /** Normalise Nigerian-style numbers to international format without "+". */
    public static function normalizePhone(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (str_starts_with($d, '0') && strlen($d) === 11) {
            $d = '234'.substr($d, 1);
        }

        return strlen($d) >= 10 && strlen($d) <= 15 ? $d : null;
    }
}
