<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight bot protection that works on shared hosting without third-party
 * services: a hidden honeypot field plus an encrypted render timestamp
 * (submissions faster than 3 seconds or older than 2 hours are rejected).
 * Combine with route throttling. A CAPTCHA can be added later if abuse appears.
 */
class SpamGuard
{
    public static function token(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public static function check(Request $request): void
    {
        $fail = fn () => throw ValidationException::withMessages(['form' => 'Your submission could not be accepted. Please wait a moment and try again.']);
        if (filled($request->input('website'))) {
            $fail();
        }
        try {
            $ts = (int) Crypt::decryptString((string) $request->input('_ft'));
        } catch (\Throwable) {
            $fail();
        }
        $age = now()->timestamp - $ts;
        if ($age < 3 || $age > 7200) {
            $fail();
        }
    }
}
