<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/account', '/business', '/rider', '/ops', '/admin/settings'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->getJson('/api/shipments')->assertUnauthorized();
    }

    public function test_roles_cannot_reach_other_portals(): void
    {
        $this->seedNetwork();
        $matrix = [
            'customer' => ['/ops', '/admin/settings', '/rider', '/admin/manage/pricing-rules'],
            'rider' => ['/ops', '/admin/settings', '/account'],
            'dispatcher' => ['/admin/settings', '/admin/users', '/admin/manage/pricing-rules', '/admin/audit', '/ops/riders/new'],
        ];
        foreach ($matrix as $role => $urls) {
            $u = $this->user($role);
            foreach ($urls as $url) {
                $this->actingAs($u)->get($url)->assertForbidden();
            }
        }
        $this->actingAs($this->user('dispatcher'))->get('/ops')->assertOk();
        $this->actingAs($this->user('admin'))->get('/admin/settings')->assertOk();
    }

    public function test_api_role_checks(): void
    {
        $this->seedNetwork();
        $c = $this->user();
        $this->actingAs($c)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->actingAs($c)->postJson('/api/rider/location', ['lat' => 6.5, 'lng' => 3.3])->assertForbidden();
        $this->actingAs($this->user('dispatcher'))->postJson('/api/admin/riders', [])->assertForbidden();
        $this->actingAs($this->user('dispatcher'))->getJson('/api/admin/dashboard')->assertOk();
    }

    public function test_public_registration_cannot_choose_a_role(): void
    {
        $this->seedNetwork();
        $this->travel(-10)->seconds();
        $token = SpamGuard::token();
        $this->travelBack();
        $this->post('/register', [
            'name' => 'Mallory', 'email' => 'm@example.com', 'phone' => '08030000000', 'password' => 'Password12345', 'password_confirmation' => 'Password12345',
            'accept_terms' => '1', 'role' => 'admin', '_ft' => $token,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'm@example.com', 'role' => 'customer']);
    }

    public function test_contact_form_spam_protection(): void
    {
        $this->seedNetwork();
        $data = ['contact_name' => 'A', 'contact_email' => 'a@example.com', 'category' => 'general', 'subject' => 'Hi', 'message' => 'Hello there, a question.'];
        // Honeypot filled
        $this->post('/contact', $data + ['website' => 'spam', '_ft' => SpamGuard::token()])->assertSessionHasErrors('form');
        // Submitted too fast
        $this->post('/contact', $data + ['_ft' => SpamGuard::token()])->assertSessionHasErrors('form');
        $this->assertDatabaseCount('support_tickets', 0);
        $this->travel(-10)->seconds();
        $t = SpamGuard::token();
        $this->travelBack();
        $this->post('/contact', $data + ['_ft' => $t])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_suspended_users_are_signed_out(): void
    {
        $u = $this->user('customer', ['status' => 'suspended']);
        $this->actingAs($u)->get('/account')->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user('customer', ['email' => 'x@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_only_admins_refund_and_refund_is_audited(): void
    {
        $this->seedNetwork();
        $p = Payment::create(['provider' => 'sandbox', 'reference' => 'PAY-R', 'amount' => '100.00', 'currency' => 'NGN', 'status' => 'successful', 'paid_at' => now()]);
        $this->actingAs($this->user('dispatcher'))->post(route('ops.payments.refund', $p), ['amount' => '10', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($this->user('admin'))->post(route('ops.payments.refund', $p), ['amount' => '100.00', 'reason' => 'Damaged'])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $p->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refund']);
        $this->actingAs($this->user('admin'))->post(route('ops.payments.refund', $p), ['amount' => '1', 'reason' => 'again'])->assertSessionHasErrors('amount');
    }
}
