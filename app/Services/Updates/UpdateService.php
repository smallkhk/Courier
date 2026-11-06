<?php

namespace App\Services\Updates;

use App\Models\AppUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * One-click updates from signed-off GitHub releases.
 *
 * Phase 1 (prepare, in the admin's request): download the release package, verify its
 * SHA-256 checksum, unpack and validate it, back up every file it will replace (plus a
 * database dump when mysqldump is available), switch on maintenance mode and copy the
 * new files in. Phase 2 (finish, in a fresh request or the scheduler so the NEW code is
 * loaded): run migrations, clear caches and bring the site back up. Any failure restores
 * the backed-up files. .env and storage/ are never touched.
 */
class UpdateService
{
    private const PROTECTED = ['.env', '.htpasswd'];

    private const STALE_STATE_MINUTES = 30;

    private ?string $root = null;

    /** The folder the site's code lives in (overridable for tests). */
    public function usingRoot(string $root): static
    {
        $this->root = rtrim($root, '/');

        return $this;
    }

    private function path(string $rel = ''): string
    {
        $root = $this->root ?? base_path();

        return $rel === '' ? $root : $root.'/'.$rel;
    }

    public function current(): string
    {
        return config('courier.version');
    }

    public function dir(string $sub = ''): string
    {
        return storage_path('app/updates'.($sub !== '' ? '/'.$sub : ''));
    }

    /** Latest published (non-draft, non-prerelease) release, cached for 30 minutes. */
    public function latest(bool $fresh = false): ?array
    {
        if ($fresh) {
            Cache::forget('updates.latest');
        }

        return Cache::remember('updates.latest', now()->addMinutes(90), fn () => $this->findLatest());
    }

    /**
     * Newest ExpressShipping release (tag vX.Y.Z with a package attached). Other projects may
     * publish releases in the same repository, so GitHub's "latest" marker is not used. If the
     * API is rate-limited (common on shared hosting IPs), falls back to the public release feed.
     */
    private function findLatest(): ?array
    {
        $repo = config('courier.updates.repository');
        $prefix = config('courier.updates.asset_prefix');
        $res = $this->github()->get("https://api.github.com/repos/{$repo}/releases", ['per_page' => 30]);
        if ($res->successful()) {
            $best = null;
            foreach ($res->json() ?? [] as $r) {
                if (! empty($r['draft']) || ! empty($r['prerelease']) || ! preg_match('/^v?(\d+\.\d+\.\d+)$/', (string) ($r['tag_name'] ?? ''), $m)) {
                    continue;
                }
                $assets = collect($r['assets'] ?? [])->keyBy('name');
                $name = $prefix.$m[1].'.zip';
                if (! $assets->has($name) || ! $assets->has($name.'.sha256')) {
                    continue; // still being built
                }
                if (! $best || version_compare($m[1], $best['version'], '>')) {
                    $best = [
                        'version' => $m[1],
                        'name' => $r['name'] ?: 'Version '.$m[1],
                        'notes' => (string) ($r['body'] ?? ''),
                        'published_at' => $r['published_at'] ?? null,
                        'html_url' => $r['html_url'] ?? null,
                        'package' => $this->assetUrl($assets[$name]),
                        'checksum' => $this->assetUrl($assets[$name.'.sha256']),
                        'size' => (int) ($assets[$name]['size'] ?? 0),
                    ];
                }
            }

            return $best;
        }

        // Fallback for public repositories: the Atom feed is not subject to API rate limits.
        if (! config('courier.updates.token')) {
            $feed = Http::timeout(20)->withHeaders(['User-Agent' => 'ExpressShipping-Updater'])->get("https://github.com/{$repo}/releases.atom");
            if ($feed->successful() && preg_match_all('#/releases/tag/v(\d+\.\d+\.\d+)"#', $feed->body(), $m)) {
                $version = collect($m[1])->unique()->sort(fn ($a, $b) => version_compare($b, $a))->first();
                $base = "https://github.com/{$repo}/releases/download/v{$version}/{$prefix}{$version}.zip";

                return [
                    'version' => $version, 'name' => 'Version '.$version, 'notes' => '', 'published_at' => null,
                    'html_url' => "https://github.com/{$repo}/releases/tag/v{$version}",
                    'package' => $base, 'checksum' => $base.'.sha256', 'size' => 0,
                ];
            }
        }

        throw new UpdateException('Could not reach GitHub to check for updates (HTTP '.$res->status().'). Try again later.');
    }

    /** The latest release if it is newer than what is installed; never throws. */
    public function available(): ?array
    {
        try {
            $latest = $this->latest();
        } catch (Throwable $e) {
            return null;
        }

        return $latest && $this->isNewer($latest['version']) ? $latest : null;
    }

    /** Like available() but only reads the cache (refreshed hourly by the scheduler): never waits on GitHub. */
    public function availableFromCache(): ?array
    {
        $latest = Cache::get('updates.latest');

        return $latest && $this->isNewer($latest['version']) ? $latest : null;
    }

    public function isNewer(string $version): bool
    {
        $current = $this->current();

        return $current === 'dev' ? false : version_compare($version, $current, '>');
    }

    public function pending(): ?array
    {
        $file = $this->dir('state.json');
        if (! is_file($file)) {
            return null;
        }

        return json_decode((string) file_get_contents($file), true) ?: null;
    }

    /** Server checks shown on the updates page. */
    public function requirements(): array
    {
        return [
            ['ZIP extension', class_exists(ZipArchive::class), 'Enable the "zip" PHP extension in cPanel → Select PHP Version.'],
            ['Site files writable', is_writable($this->path()) && is_writable($this->path('vendor')) && is_writable($this->path('app')), 'The site folder must be writable by PHP (the default on cPanel).'],
            ['Storage writable', is_writable(storage_path('app')), 'storage/app must be writable.'],
            ['Database backup (mysqldump)', $this->mysqldump() !== null, 'Optional. Without it, back up the database in cPanel → Backup before updating.', true],
        ];
    }

    /**
     * Phase 1. Returns the state to hand to finish(). Throws UpdateException with a
     * human-readable message; the live site is restored on any failure after files move.
     */
    public function prepare(array $release, ?User $user, ?callable $log = null): array
    {
        $log ??= fn () => null;
        $version = $release['version'];
        if (! $this->isNewer($version)) {
            throw new UpdateException("Version {$version} is not newer than the installed version {$this->current()}.");
        }
        if (($p = $this->pending()) && now()->diffInMinutes($p['started_at'], true) < self::STALE_STATE_MINUTES) {
            throw new UpdateException('Another update is already in progress.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new UpdateException('The PHP zip extension is required.');
        }
        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->ensureDir($this->dir('downloads'));
        $zip = $this->dir("downloads/{$version}.zip");
        $log("Downloading version {$version}…");
        $this->download($release['package'], $zip);
        $expected = $this->fetchChecksum($release['checksum']);
        if (! hash_equals($expected, hash_file('sha256', $zip))) {
            @unlink($zip);
            throw new UpdateException('The downloaded package failed its checksum check, so it was discarded. Nothing was changed.');
        }
        $log('Checksum verified.');

        $staging = $this->dir("staging-{$version}");
        $this->deleteTree($staging);
        $this->extract($zip, $staging);
        $manifest = $this->validatePackage($staging, $version);
        $log('Package unpacked and validated ('.count($manifest['files']).' files).');

        $backupId = $this->current().'-to-'.$version.'-'.now()->format('Ymd-His');
        $backup = $this->dir("backups/{$backupId}");
        [$changed, $removed] = $this->plan($staging, $manifest);
        $log(count($changed).' files change, '.count($removed).' removed.');

        $added = $this->backupFiles($backup, $changed, $removed);
        $db = $this->backupDatabase($backup);
        $log($db ? 'Database backed up.' : 'Database backup skipped (mysqldump not available).');
        file_put_contents("{$backup}/backup.json", json_encode([
            'id' => $backupId, 'from' => $this->current(), 'to' => $version, 'created_at' => now()->toIso8601String(),
            'added' => $added, 'database' => $db,
        ], JSON_PRETTY_PRINT));

        $secret = Str::random(40);
        Artisan::call('down', ['--retry' => 30, '--secret' => $secret]);
        try {
            foreach ($changed as $rel) {
                $this->place("{$staging}/{$rel}", $this->path($rel));
            }
            foreach ($removed as $rel) {
                @unlink($this->path($rel));
            }
        } catch (Throwable $e) {
            $this->restore($backup);
            Artisan::call('up');
            $this->record('update', $this->current(), $version, 'failed', $user, $e->getMessage(), $backupId);
            throw new UpdateException('Copying the new files failed, so the previous version was restored: '.$e->getMessage());
        }
        $this->resetOpcache();
        $log('New files installed. Finishing…');

        $state = [
            'status' => 'files_installed', 'from' => $this->current(), 'to' => $version, 'backup' => $backupId,
            'user_id' => $user?->id, 'started_at' => now()->toIso8601String(), 'database_backup' => (bool) $db,
        ];
        file_put_contents($this->dir('state.json'), json_encode($state, JSON_PRETTY_PRINT));
        $this->deleteTree($staging);
        @unlink($zip);

        return $state;
    }

    /** Phase 2 — must run in a fresh PHP process/request so the new code is loaded. */
    public function finish(): AppUpdate
    {
        $state = $this->pending();
        if (! $state || $state['status'] !== 'files_installed') {
            throw new UpdateException('There is no update waiting to be finished.');
        }
        $user = $state['user_id'] ? User::find($state['user_id']) : null;
        @set_time_limit(0);
        @ignore_user_abort(true);
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $this->restore($this->dir('backups/'.$state['backup']));
            $this->resetOpcache();
            $this->clearCaches();
            Artisan::call('up');
            @unlink($this->dir('state.json'));
            $this->record('update', $state['from'], $state['to'], 'failed', $user, 'Database update failed: '.$e->getMessage(), $state['backup']);
            Log::error('Update to '.$state['to'].' failed during migration', ['exception' => $e]);
            throw new UpdateException('The database step failed, so the previous version\'s files were restored. '
                .($state['database_backup'] ? 'A database backup was taken before the update.' : '').' Details: '.$e->getMessage());
        }
        $this->clearCaches();
        Artisan::call('up');
        @unlink($this->dir('state.json'));
        $this->pruneBackups();

        return $this->record('update', $state['from'], $state['to'], 'completed', $user, trim($output), $state['backup']);
    }

    /** Restore the files from a backup (the database is not changed). */
    public function rollback(string $backupId, ?User $user): AppUpdate
    {
        $meta = collect($this->backups())->firstWhere('id', $backupId);
        if (! $meta) {
            throw new UpdateException('That backup no longer exists.');
        }
        if ($this->pending()) {
            throw new UpdateException('An update is in progress; wait for it to finish first.');
        }
        @set_time_limit(0);
        Artisan::call('down', ['--retry' => 30]);
        try {
            $this->restore($this->dir('backups/'.$backupId));
        } finally {
            $this->resetOpcache();
            $this->clearCaches();
            Artisan::call('up');
        }

        return $this->record('rollback', $meta['to'], $meta['from'], 'completed', $user, 'Files restored from backup '.$backupId.'. The database was not changed.', $backupId);
    }

    /** @return list<array{id:string, from:string, to:string, created_at:string, database:bool}> */
    public function backups(): array
    {
        return collect(glob($this->dir('backups/*/backup.json')) ?: [])
            ->map(fn ($f) => json_decode((string) file_get_contents($f), true))
            ->filter()
            ->map(fn ($m) => ['id' => $m['id'], 'from' => $m['from'], 'to' => $m['to'], 'created_at' => $m['created_at'], 'database' => (bool) ($m['database'] ?? false)])
            ->sortByDesc('created_at')->values()->all();
    }

    // ------------------------------------------------------------------

    private function github()
    {
        $http = Http::timeout(20)->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'ExpressShipping-Updater']);

        return ($t = config('courier.updates.token')) ? $http->withToken($t) : $http;
    }

    /** Private repositories need the API asset URL + token; public ones use the plain download URL. */
    private function assetUrl(array $asset): string
    {
        return config('courier.updates.token') ? $asset['url'] : $asset['browser_download_url'];
    }

    private function download(string $url, string $to): void
    {
        $res = $this->github()->timeout(300)->withHeaders(['Accept' => 'application/octet-stream'])->get($url);
        if (! $res->successful()) {
            throw new UpdateException('Download failed (HTTP '.$res->status().'). Nothing was changed.');
        }
        file_put_contents($to, $res->body());
    }

    private function fetchChecksum(string $url): string
    {
        $res = $this->github()->withHeaders(['Accept' => 'application/octet-stream'])->get($url);
        if (! $res->successful() || ! preg_match('/\b([a-f0-9]{64})\b/i', $res->body(), $m)) {
            throw new UpdateException('Could not read the package checksum. Nothing was changed.');
        }

        return strtolower($m[1]);
    }

    private function extract(string $zip, string $to): void
    {
        $za = new ZipArchive;
        if ($za->open($zip) !== true) {
            throw new UpdateException('The package is not a valid ZIP file. Nothing was changed.');
        }
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = (string) $za->getNameIndex($i);
            if (! $this->safePath($name)) {
                $za->close();
                throw new UpdateException('The package contains an unsafe path and was rejected. Nothing was changed.');
            }
        }
        $this->ensureDir($to);
        $ok = $za->extractTo($to);
        $za->close();
        if (! $ok) {
            throw new UpdateException('Unpacking the package failed (is the disk full?). Nothing was changed.');
        }
    }

    public function safePath(string $rel): bool
    {
        return $rel !== ''
            && ! str_contains($rel, "\0") && ! str_contains($rel, '\\')
            && ! str_starts_with($rel, '/') && ! preg_match('/^[a-zA-Z]:/', $rel)
            && ! in_array('..', explode('/', $rel), true);
    }

    private function validatePackage(string $staging, string $version): array
    {
        foreach (['artisan', 'VERSION', 'manifest.json', 'vendor/autoload.php', 'public/index.php'] as $f) {
            if (! is_file("{$staging}/{$f}")) {
                throw new UpdateException("The package is incomplete (missing {$f}). Nothing was changed.");
            }
        }
        if (trim((string) file_get_contents("{$staging}/VERSION")) !== $version) {
            throw new UpdateException('The package version does not match the release. Nothing was changed.');
        }
        $manifest = json_decode((string) file_get_contents("{$staging}/manifest.json"), true);
        if (! is_array($manifest['files'] ?? null)) {
            throw new UpdateException('The package manifest is unreadable. Nothing was changed.');
        }
        foreach ($manifest['files'] as $rel => $hash) {
            if (! $this->safePath($rel) || ! is_file("{$staging}/{$rel}")) {
                throw new UpdateException("The package manifest does not match its contents ({$rel}). Nothing was changed.");
            }
        }

        return $manifest;
    }

    /** @return array{0: list<string>, 1: list<string>} files to write, files to delete */
    private function plan(string $staging, array $manifest): array
    {
        $changed = [];
        foreach ($manifest['files'] as $rel => $hash) {
            if ($this->isProtected($rel)) {
                continue;
            }
            if (str_starts_with($rel, 'storage/') && file_exists($this->path($rel))) {
                continue; // storage skeleton only; never overwrite runtime data
            }
            $target = $this->path($rel);
            if (! is_file($target) || ! hash_equals($hash, (string) hash_file('sha256', $target))) {
                $changed[] = $rel;
            }
        }
        $removed = [];
        $old = is_file($this->path('manifest.json')) ? json_decode((string) file_get_contents($this->path('manifest.json')), true) : null;
        foreach (array_keys($old['files'] ?? []) as $rel) {
            if (! isset($manifest['files'][$rel]) && $this->safePath($rel) && ! $this->isProtected($rel)
                && ! str_starts_with($rel, 'storage/') && is_file($this->path($rel))) {
                $removed[] = $rel;
            }
        }

        return [$changed, $removed];
    }

    private function isProtected(string $rel): bool
    {
        return in_array($rel, self::PROTECTED, true) || str_starts_with($rel, 'public/storage/');
    }

    /** Copies files that will be overwritten or deleted; returns the files that are new. */
    private function backupFiles(string $backup, array $changed, array $removed): array
    {
        $added = [];
        foreach (array_merge($changed, $removed) as $rel) {
            $src = $this->path($rel);
            if (is_file($src)) {
                $this->ensureDir(dirname("{$backup}/files/{$rel}"));
                if (! copy($src, "{$backup}/files/{$rel}")) {
                    throw new UpdateException("Could not back up {$rel} (is the disk full?). Nothing was changed.");
                }
            } else {
                $added[] = $rel;
            }
        }

        return $added;
    }

    private function restore(string $backup): void
    {
        $meta = json_decode((string) @file_get_contents("{$backup}/backup.json"), true) ?: [];
        $files = "{$backup}/files";
        if (is_dir($files)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($files, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = substr($f->getPathname(), strlen($files) + 1);
                $this->place($f->getPathname(), $this->path($rel));
            }
        }
        foreach ($meta['added'] ?? [] as $rel) {
            if ($this->safePath($rel) && ! $this->isProtected($rel)) {
                @unlink($this->path($rel));
            }
        }
    }

    /** Write via a temp file + rename so a file is never half-written. */
    private function place(string $from, string $to): void
    {
        $this->ensureDir(dirname($to));
        $tmp = $to.'.upd-'.Str::random(6);
        if (! copy($from, $tmp)) {
            throw new UpdateException('Could not write '.$to);
        }
        @chmod($tmp, fileperms($from) & 0777);
        if (! rename($tmp, $to)) {
            @unlink($tmp);
            throw new UpdateException('Could not replace '.$to);
        }
    }

    private function mysqldump(): ?string
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            return null;
        }
        if (! function_exists('proc_open') || in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump'] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function backupDatabase(string $backup): bool
    {
        $bin = $this->mysqldump();
        if (! $bin) {
            return false;
        }
        $c = config('database.connections.'.config('database.default'));
        $this->ensureDir($backup);
        $cnf = "{$backup}/.my.cnf";
        file_put_contents($cnf, "[client]\nuser=\"{$c['username']}\"\npassword=\"".addcslashes((string) $c['password'], '"\\')."\"\nhost=\"{$c['host']}\"\nport={$c['port']}\n");
        @chmod($cnf, 0600);
        try {
            $sql = "{$backup}/database.sql";
            $r = Process::timeout(600)->run([$bin, "--defaults-extra-file={$cnf}", '--single-transaction', '--quick', '--no-tablespaces', "--result-file={$sql}", $c['database']]);
            if (! $r->successful() || ! is_file($sql)) {
                Log::warning('Pre-update database backup failed', ['error' => $r->errorOutput()]);

                return false;
            }
            $in = fopen($sql, 'rb');
            $out = gzopen("{$sql}.gz", 'wb6');
            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 1 << 20));
            }
            fclose($in);
            gzclose($out);
            @unlink($sql);

            return true;
        } catch (Throwable $e) {
            Log::warning('Pre-update database backup failed', ['exception' => $e]);

            return false;
        } finally {
            @unlink($cnf);
        }
    }

    private function clearCaches(): void
    {
        foreach (['view:clear', 'route:clear', 'config:clear', 'event:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (Throwable) {
            }
        }
        Cache::forget('updates.latest');
    }

    private function resetOpcache(): void
    {
        clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneBackups(int $keep = 3): void
    {
        foreach (array_slice($this->backups(), $keep) as $b) {
            $this->deleteTree($this->dir('backups/'.$b['id']));
        }
    }

    private function record(string $action, string $from, string $to, string $status, ?User $user, string $log, ?string $backup): AppUpdate
    {
        return AppUpdate::create([
            'action' => $action, 'from_version' => $from, 'to_version' => $to, 'status' => $status,
            'user_id' => $user?->id, 'log' => Str::limit($log, 10000), 'backup' => $backup,
        ]);
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new UpdateException("Could not create {$dir}.");
        }
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
