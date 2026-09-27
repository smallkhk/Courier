<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\NotificationLog;
use App\Models\RiderLocation;
use App\Services\BookingService;
use App\Services\Notifications\NotificationService;
use App\Services\PricingService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationAndLocationTest extends TestCase
{
    use RefreshDatabase;

    private function booked()
    {
        $this->seedNetwork();
        $u = $this->user('customer', ['sms_consent_at' => now()]);
        $q = app(PricingService::class)->quote(BookingController::quoteInput($this->normalizedPayload()), $u->id);

        return app(BookingService::class)->create($this->normalizedPayload(), $q, $u, null, 'online', 'n1')['shipment'];
    }

    public function test_notifications_are_deduplicated(): void
    {
        $s = $this->booked();
        $before = NotificationLog::count();
        app(NotificationService::class)->forShipment($s, 'booking_created');
        app(NotificationService::class)->forShipment($s, 'booking_created');
        $this->assertSame($before, NotificationLog::count());
    }

    public function test_emails_are_sent_by_the_scheduler_job(): void
    {
        Mail::fake();
        $this->booked();
        $this->artisan('notifications:send')->assertSuccessful();
        $this->assertSame(0, NotificationLog::where('channel', 'email')->where('status', 'queued')->count());
        $this->assertTrue(NotificationLog::where('channel', 'email')->where('status', 'sent')->exists());
        $body = NotificationLog::where('channel', 'email')->first()->body;
        $this->assertStringNotContainsString('password', strtolower($body));
    }

    public function test_provider_failures_retry_with_backoff_then_fail(): void
    {
        config(['courier.sms.driver' => 'termii', 'courier.sms.termii.api_key' => 'k', 'courier.sms.termii.sender_id' => 'Courier']);
        Settings::set('sms_enabled', true);
        Http::fake(['*' => Http::response('down', 503)]);
        $this->booked();
        $log = NotificationLog::where('channel', 'sms')->firstOrFail();

        app(NotificationService::class)->processDue();
        $log->refresh();
        $this->assertSame('queued', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertTrue($log->next_attempt_at->isFuture(), 'Retry is scheduled with backoff.');

        for ($i = 0; $i < NotificationService::MAX_ATTEMPTS; $i++) {
            NotificationLog::whereKey($log->id)->update(['next_attempt_at' => now()->subSecond()]);
            app(NotificationService::class)->processDue();
        }
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(NotificationService::MAX_ATTEMPTS, $log->fresh()->attempts);
    }

    public function test_permanent_failures_do_not_retry(): void
    {
        config(['courier.sms.driver' => 'termii', 'courier.sms.termii.api_key' => 'k', 'courier.sms.termii.sender_id' => 'Courier']);
        Settings::set('sms_enabled', true);
        Http::fake(['*' => Http::response(['message' => 'bad'], 400)]);
        $this->booked();
        app(NotificationService::class)->processDue();
        $this->assertSame('failed', NotificationLog::where('channel', 'sms')->first()->status);
    }

    public function test_disabled_channel_is_recorded_as_skipped(): void
    {
        Settings::set('sms_enabled', false);
        $this->booked();
        $this->assertSame('skipped', NotificationLog::where('channel', 'sms')->first()->status);
    }

    public function test_location_rules(): void
    {
        $this->seedNetwork();
        $rider = $this->user('rider');
        $p = $rider->riderProfile;

        // Off duty / no consent: refused.
        $p->update(['on_duty' => false]);
        $this->actingAs($rider)->postJson('/api/rider/location-sharing', ['sharing' => true, 'consent' => true])->assertStatus(422);
        $p->update(['on_duty' => true]);
        $this->actingAs($rider)->postJson('/api/rider/location', ['lat' => 6.5, 'lng' => 3.3])->assertStatus(422);

        // Consent + on duty: accepted.
        $this->actingAs($rider)->postJson('/api/rider/location-sharing', ['sharing' => true, 'consent' => true])->assertOk();
        $this->actingAs($rider)->postJson('/api/rider/location', ['lat' => 6.5, 'lng' => 3.3, 'accuracy' => 12])->assertCreated();
        $this->assertSame('live', $p->fresh()->locationFreshness());

        // Invalid and implausible updates rejected.
        $this->actingAs($rider)->postJson('/api/rider/location', ['lat' => 95, 'lng' => 3.3])->assertStatus(422);
        $this->actingAs($rider)->postJson('/api/rider/location', ['lat' => 9.07, 'lng' => 7.48])->assertStatus(422); // Lagos→Abuja in seconds

        // Going off duty stops sharing.
        $this->actingAs($rider)->postJson('/api/rider/availability', ['on_duty' => false])->assertOk()->assertJson(['location_sharing' => false]);
        $this->actingAs($rider)->postJson('/api/rider/location', ['lat' => 6.5, 'lng' => 3.3])->assertStatus(422);

        // Stale data is never labelled live.
        $this->travel(Settings::get('location_stale_minutes') + 1)->minutes();
        $this->assertSame('stale', $p->fresh()->locationFreshness());
    }

    public function test_location_retention(): void
    {
        $this->seedNetwork();
        RiderLocation::create(['rider_id' => $this->user('rider')->id, 'lat' => 6.5, 'lng' => 3.3, 'recorded_at' => now()->subDays(40), 'received_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
        $this->artisan('courier:prune')->assertSuccessful();
        $this->assertDatabaseCount('rider_locations', 0);
    }
}
