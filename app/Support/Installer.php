<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** State of the first-launch web installer (/install). */
class Installer
{
    public static function lockFile(): string
    {
        return storage_path('app/installed.json');
    }

    public static function installed(): bool
    {
        if (is_file(self::lockFile())) {
            return true;
        }
        try {
            return DB::getSchemaBuilder()->hasTable('users') && User::where('role', 'admin')->exists();
        } catch (Throwable) {
            return false; // no database yet
        }
    }

    /** The installer is reachable only before install, and only when INSTALL_TOKEN is set. */
    public static function enabled(): bool
    {
        return strlen((string) config('courier.install_token')) >= 20 && ! self::installed();
    }

    /**
     * Tables left by an earlier attempt that stopped half-way: the database has tables but
     * not every migration has run. A complete schema (or an empty database) returns [].
     */
    public static function existingTables(): array
    {
        try {
            $tables = array_values(array_filter(array_map(fn ($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t, DB::getSchemaBuilder()->getTableListing(DB::connection()->getDatabaseName(), false))));
            if (array_diff($tables, ['migrations']) === []) {
                return [];
            }
            $ran = in_array('migrations', $tables, true) ? DB::table('migrations')->pluck('migration')->all() : [];
            $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);

            return array_diff($files, $ran) === [] ? [] : $tables;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array{0:string, 1:bool, 2:string}> [check, passed, how to fix] */
    public static function requirements(): array
    {
        $ext = fn (string $e) => extension_loaded($e);
        $db = false;
        $dbError = 'Check DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env, and that the user is added to the database in cPanel → MySQL Databases with ALL PRIVILEGES.';
        try {
            DB::connection()->getPdo();
            $db = true;
        } catch (Throwable $e) {
            $dbError = 'Cannot connect: '.$e->getMessage().'. '.$dbError;
        }

        return [
            ['PHP 8.2 or newer (you have '.PHP_VERSION.')', version_compare(PHP_VERSION, '8.2.0', '>='), 'cPanel → Select PHP Version (or MultiPHP Manager) → choose PHP 8.3.'],
            ['PHP extensions', collect(['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'curl', 'zip', 'intl', 'gd'])->every($ext),
                'Enable these in cPanel → Select PHP Version → Extensions: '.collect(['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'curl', 'zip', 'intl', 'gd'])->reject($ext)->implode(', ')],
            ['Folders writable (storage, bootstrap/cache)', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'In File Manager, set storage/ and bootstrap/cache/ to permission 755 (folders) — PHP must be able to write there.'],
            ['Secure app key (APP_KEY)', (bool) config('app.key'), 'The .env file is missing APP_KEY. Use the .env file prepared for you.'],
            ['HTTPS site address (APP_URL)', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL=https://yourdomain in .env and enable SSL in cPanel → SSL/TLS Status (AutoSSL).'],
            ['Database connection', $db, $dbError],
        ];
    }
}
