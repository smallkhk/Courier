<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'setup-code-1234567890-abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(Installer::lockFile());
        config(['courier.install_token' => self::TOKEN, 'app.url' => 'https://ship.example.com']);
    }

    protected function tearDown(): void
    {
        @unlink(Installer::lockFile());
        parent::tearDown();
    }

    private function form(array $override = []): array
    {
        return array_merge([
            'install_token' => self::TOKEN, 'business_name' => 'ExpressShipping', 'support_email' => 'help@ship.example.com',
            'name' => 'Owner Person', 'email' => 'Owner@Ship.example.com', 'password' => 'correct-horse-42', 'password_confirmation' => 'correct-horse-42',
        ], $override);
    }

    public function test_fresh_site_sends_visitors_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Welcome to ExpressShipping')->assertSee('Database connection');
    }

    public function test_wrong_setup_code_is_refused(): void
    {
        $this->post('/install', $this->form(['install_token' => 'nope']))->assertSessionHasErrors('install_token');
        $this->assertSame(0, User::count());
    }

    public function test_install_creates_admin_and_locks_itself_without_demo_data(): void
    {
        $this->post('/install', $this->form())->assertRedirect('/login');

        $admin = User::where('email', 'owner@ship.example.com')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame('ExpressShipping', Settings::get('business_name'));
        $this->assertSame(1, User::count(), 'no demo accounts');
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('notification_templates', ['event' => 'delivered', 'channel' => 'email']);
        $this->assertFileExists(Installer::lockFile());

        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->form(['email' => 'second@ship.example.com']))->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_installer_is_off_without_a_token_or_once_an_admin_exists(): void
    {
        config(['courier.install_token' => null]);
        $this->get('/install')->assertNotFound();
        $this->get('/')->assertOk();

        config(['courier.install_token' => self::TOKEN]);
        $this->user('admin');
        $this->get('/install')->assertNotFound();
    }
}
