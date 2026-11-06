<?php

namespace Tests\Feature;

use App\Models\AppUpdate;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        config(['courier.version' => '1.0.0', 'courier.updates.repository' => 'acme/site', 'courier.updates.token' => null]);
        $this->work = sys_get_temp_dir().'/upd-'.uniqid();
        $this->root = $this->work.'/site';
        $this->installSite($this->root, '1.0.0', [
            'public/index.php' => '<?php // v1',
            'app/Same.php' => '<?php // same',
            'app/Old.php' => '<?php // removed in 1.1.0',
        ]);
        file_put_contents($this->root.'/.env', 'APP_KEY=secret-original');
        File::deleteDirectory(storage_path('app/updates'));
        $this->app->instance(UpdateService::class, (new UpdateService)->usingRoot($this->root));
    }

    protected function tearDown(): void
    {
        Artisan::call('up');
        File::deleteDirectory($this->work);
        File::deleteDirectory(storage_path('app/updates'));
        parent::tearDown();
    }

    /** Writes a site folder with a manifest, like an installed release package. */
    private function installSite(string $dir, string $version, array $files): void
    {
        $files += ['artisan' => '#!/usr/bin/env php', 'vendor/autoload.php' => '<?php', 'VERSION' => $version."\n"];
        $hashes = [];
        foreach ($files as $rel => $body) {
            File::ensureDirectoryExists(dirname("{$dir}/{$rel}"));
            file_put_contents("{$dir}/{$rel}", $body);
            $hashes[$rel] = hash('sha256', $body);
        }
        ksort($hashes);
        file_put_contents("{$dir}/manifest.json", json_encode(['version' => $version, 'files' => $hashes]));
    }

    /** Builds a release ZIP and fakes the GitHub API + downloads for it. */
    private function publish(string $version, array $files, ?string $checksum = null, array $extraEntries = []): void
    {
        $src = $this->work."/release-{$version}";
        $this->installSite($src, $version, $files);
        $zipPath = $this->work."/expressshipping-{$version}.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (File::allFiles($src, true) as $f) {
            $zip->addFile($f->getPathname(), str_replace('\\', '/', $f->getRelativePathname()));
        }
        foreach ($extraEntries as $name => $body) {
            $zip->addFromString($name, $body);
        }
        $zip->close();
        $bytes = file_get_contents($zipPath);
        $name = "expressshipping-{$version}.zip";

        Http::fake([
            'api.github.com/repos/acme/site/releases*' => Http::response([
                // Another project's release in the same repository must be ignored.
                ['tag_name' => 'voice-latest', 'name' => 'Voice', 'draft' => false, 'prerelease' => false, 'assets' => [['name' => 'voice.zip', 'url' => 'x', 'browser_download_url' => 'x']]],
                ['tag_name' => 'v9.9.9', 'name' => 'Unfinished build', 'draft' => false, 'prerelease' => false, 'assets' => []],
                ['tag_name' => "v{$version}", 'draft' => false, 'prerelease' => false, 'name' => "ExpressShipping {$version}", 'body' => '- Faster tracking',
                    'published_at' => now()->toIso8601String(), 'html_url' => 'https://github.com/acme/site/releases/v'.$version,
                    'assets' => [
                        ['name' => $name, 'size' => strlen($bytes), 'url' => 'https://api.github.com/assets/1', 'browser_download_url' => "https://github.com/acme/site/releases/download/v{$version}/{$name}"],
                        ['name' => "{$name}.sha256", 'size' => 90, 'url' => 'https://api.github.com/assets/2', 'browser_download_url' => "https://github.com/acme/site/releases/download/v{$version}/{$name}.sha256"],
                    ],
                ]]),
            "github.com/acme/site/releases/download/v{$version}/{$name}.sha256" => Http::response(($checksum ?? hash('sha256', $bytes))."  {$name}\n"),
            "github.com/acme/site/releases/download/v{$version}/{$name}" => Http::response($bytes),
        ]);
    }

    private function release110(): void
    {
        $this->publish('1.1.0', [
            'public/index.php' => '<?php // v2',
            'app/Same.php' => '<?php // same',
            'app/New.php' => '<?php // added in 1.1.0',
            '.env' => 'APP_KEY=attacker', // must never overwrite the live .env
        ]);
    }

    public function test_update_installs_changed_files_removes_stale_ones_and_keeps_env(): void
    {
        $this->release110();
        $svc = app(UpdateService::class);
        $release = $svc->latest(fresh: true);
        $this->assertSame('1.1.0', $release['version']);
        $this->assertTrue($svc->isNewer('1.1.0'));

        $state = $svc->prepare($release, null);
        $this->assertSame('files_installed', $state['status']);
        $this->assertTrue(app()->isDownForMaintenance(), 'site is in maintenance while finishing');
        $this->assertSame('<?php // v2', file_get_contents($this->root.'/public/index.php'));
        $this->assertFileExists($this->root.'/app/New.php');
        $this->assertFileDoesNotExist($this->root.'/app/Old.php');
        $this->assertSame('APP_KEY=secret-original', file_get_contents($this->root.'/.env'));
        $this->assertSame("1.1.0\n", file_get_contents($this->root.'/VERSION'));

        $done = $svc->finish();
        $this->assertSame('completed', $done->status);
        $this->assertSame(['1.0.0', '1.1.0'], [$done->from_version, $done->to_version]);
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertNull($svc->pending());

        // Roll back to 1.0.0 from the automatic backup.
        config(['courier.version' => '1.1.0']);
        $backup = $svc->backups()[0];
        $this->assertSame(['1.0.0', '1.1.0'], [$backup['from'], $backup['to']]);
        $svc->rollback($backup['id'], null);
        $this->assertSame('<?php // v1', file_get_contents($this->root.'/public/index.php'));
        $this->assertFileExists($this->root.'/app/Old.php');
        $this->assertFileDoesNotExist($this->root.'/app/New.php');
        $this->assertSame("1.0.0\n", file_get_contents($this->root.'/VERSION'));
        $this->assertSame('APP_KEY=secret-original', file_get_contents($this->root.'/.env'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame(2, AppUpdate::count());
    }

    public function test_tampered_package_is_rejected_and_nothing_changes(): void
    {
        $this->publish('1.1.0', ['public/index.php' => '<?php // evil'], checksum: str_repeat('a', 64));
        $svc = app(UpdateService::class);
        try {
            $svc->prepare($svc->latest(fresh: true), null);
            $this->fail('Expected checksum failure');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
        }
        $this->assertSame('<?php // v1', file_get_contents($this->root.'/public/index.php'));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_package_with_path_traversal_is_rejected(): void
    {
        $this->publish('1.1.0', ['public/index.php' => '<?php // v2'], extraEntries: ['../escape.php' => '<?php']);
        $svc = app(UpdateService::class);
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('unsafe path');
        try {
            $svc->prepare($svc->latest(fresh: true), null);
        } finally {
            $this->assertFileDoesNotExist($this->work.'/escape.php');
            $this->assertSame('<?php // v1', file_get_contents($this->root.'/public/index.php'));
        }
    }

    public function test_rate_limited_api_falls_back_to_the_release_feed(): void
    {
        $this->publish('1.1.0', ['public/index.php' => '<?php // v2']);
        Http::swap(new Factory);
        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403),
            'github.com/acme/site/releases.atom' => Http::response('<feed><entry><link href="https://github.com/acme/site/releases/tag/v1.1.0"/></entry><entry><link href="https://github.com/acme/site/releases/tag/voice-latest"/></entry></feed>'),
        ]);
        $latest = app(UpdateService::class)->latest(fresh: true);
        $this->assertSame('1.1.0', $latest['version']);
        $this->assertSame('https://github.com/acme/site/releases/download/v1.1.0/expressshipping-1.1.0.zip', $latest['package']);
    }

    public function test_older_or_same_version_is_never_installed(): void
    {
        $this->publish('1.0.0', ['public/index.php' => '<?php // v1']);
        $svc = app(UpdateService::class);
        $this->assertNull($svc->available());
        $this->expectException(UpdateException::class);
        $svc->prepare($svc->latest(fresh: true), null);
    }

    public function test_admin_installs_update_from_the_browser_in_two_steps(): void
    {
        $this->release110();
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/updates')->assertOk()->assertSee('1.1.0')->assertSee('Install update');
        $this->actingAs($admin)->post('/admin/updates/install', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame('<?php // v1', file_get_contents($this->root.'/public/index.php'));

        $this->actingAs($admin)->post('/admin/updates/install', ['current_password' => 'password'])
            ->assertOk()->assertSee('Installing version 1.1.0');
        $this->get('/')->assertStatus(503); // visitors see maintenance while it finishes

        // The finishing step is allowed through maintenance mode.
        $this->actingAs($admin)->post('/admin/updates/finish')->assertRedirect('/admin/updates')->assertSessionHas('success');
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame('<?php // v2', file_get_contents($this->root.'/public/index.php'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.updated']);
    }

    public function test_updates_page_works_on_the_real_site_folder(): void
    {
        $this->app->forgetInstance(UpdateService::class);
        Http::fake(['api.github.com/*' => Http::response([])]);
        $this->actingAs($this->user('admin'))->get('/admin/updates')->assertOk()->assertSee('Server checks')->assertSee('No releases have been published yet.');
    }

    public function test_only_admins_can_reach_updates(): void
    {
        $this->actingAs($this->user('dispatcher'))->get('/admin/updates')->assertForbidden();
        $this->actingAs($this->user('dispatcher'))->post('/admin/updates/install', ['current_password' => 'password'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post('/admin/updates/finish')->assertRedirect('/login');
    }

    public function test_scheduler_finishes_an_abandoned_update(): void
    {
        $this->release110();
        $svc = app(UpdateService::class);
        $svc->prepare($svc->latest(fresh: true), null);
        $this->artisan('courier:finish-update')->assertSuccessful(); // too soon: browser gets a head start
        $this->assertNotNull($svc->pending());
        $this->travel(2)->minutes();
        $this->artisan('courier:finish-update')->assertSuccessful();
        $this->assertNull($svc->pending());
        $this->assertFalse(app()->isDownForMaintenance());
    }
}
