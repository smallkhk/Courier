<?php

namespace App\Support;

use App\Models\ContentBlock;
use App\Models\PricingRule;
use App\Models\Service;
use App\Models\ServiceZone;

/** What still needs doing before the site can take real bookings (Admin → System settings). */
class LaunchChecklist
{
    /** @return list<array{done:bool, title:string, help:string, url:?string}> */
    public static function items(): array
    {
        $manage = fn (string $r) => route('admin.resource.index', ['resource' => $r]);
        $drafts = ContentBlock::whereIn('key', ['terms', 'privacy', 'delivery-policy'])->where('body', 'like', '%DRAFT%')->pluck('title');
        $stripe = (string) config('courier.payments.stripe.secret_key');

        return [
            ['done' => ServiceZone::where('active', true)->exists(), 'title' => 'Add the areas you serve',
                'help' => 'Coverage zones by country, state or ZIP prefix (e.g. "New York City" = ZIPs starting 100–104, 110–114, 116).', 'url' => $manage('zones')],
            ['done' => Service::where('active', true)->exists(), 'title' => 'Create your delivery services',
                'help' => 'For example Same-day, Next-day, Ground and International, with the zones each one covers.', 'url' => $manage('services')],
            ['done' => PricingRule::where('active', true)->exists(), 'title' => 'Set your prices',
                'help' => 'A base fee, included weight and price per extra lb for each service. Customers are quoted from these.', 'url' => $manage('pricing-rules')],
            ['done' => $drafts->isEmpty(), 'title' => 'Replace the draft policies',
                'help' => $drafts->isEmpty() ? 'Terms, privacy and delivery policies are written.' : 'Still drafts: '.$drafts->implode(', ').'. Have them reviewed by a lawyer.', 'url' => $manage('content')],
            ['done' => config('courier.payments.provider') === 'stripe' && str_starts_with($stripe, 'sk_live_') && config('courier.payments.stripe.webhook_secret'),
                'title' => 'Connect Stripe (live keys)', 'help' => 'STRIPE_KEY, STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET in .env — see DEPLOYMENT.md §7.', 'url' => null],
            ['done' => ! in_array(config('mail.default'), ['log', 'array'], true), 'title' => 'Send real emails',
                'help' => 'Use your cPanel email account (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD in .env).', 'url' => null],
            ['done' => (bool) cache('scheduler:last_run'), 'title' => 'Turn on the scheduled task (cron)',
                'help' => 'cPanel → Cron Jobs → every minute. It sends notifications, checks payments and finishes updates.', 'url' => null],
            ['done' => (bool) config('courier.address_autocomplete.google_key'), 'title' => 'Add Google address search (recommended)',
                'help' => 'GOOGLE_MAPS_API_KEY in .env. Until then, address search uses OpenStreetMap.', 'url' => null],
            ['done' => config('courier.sms.driver') !== 'log', 'title' => 'Text-message updates (optional)',
                'help' => 'Twilio keys in .env (US numbers need A2P 10DLC registration).', 'url' => null],
        ];
    }
}
