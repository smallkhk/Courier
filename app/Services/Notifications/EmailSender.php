<?php

namespace App\Services\Notifications;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailSender
{
    /** @return array{provider:string, id:?string} */
    public function send(string $to, string $subject, string $body): array
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new PermanentDeliveryFailure('Invalid email address.');
        }
        $id = (string) Str::uuid();
        $html = view('emails.notification', ['subject' => $subject, 'body' => $body])->render();
        Mail::html($html, function (Message $m) use ($to, $subject, $id) {
            $m->to($to)->subject($subject);
            $m->getHeaders()->addTextHeader('X-Courier-Message-Id', $id);
        });

        // With MAIL_MAILER=log the message is written to storage/logs (development only).
        return ['provider' => 'mail:'.config('mail.default'), 'id' => $id];
    }
}
