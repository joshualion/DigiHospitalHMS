<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReleasePreflight extends Command
{
    protected $signature = 'hms:preflight {--production : Enforce production safeguards} {--json : Machine-readable result}';
    protected $description = 'Read-only deployment and upgrade prerequisite checks; never changes application data';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $checks = [];
        $check = function (string $name, bool $passed, string $detail) use (&$checks): void {
            $checks[] = ['check' => $name, 'passed' => $passed, 'detail' => $detail];
        };

        $check('php-version', PHP_VERSION_ID >= 80200, 'PHP 8.2+ required');
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml'] as $extension) {
            $check('extension-'.$extension, extension_loaded($extension), $extension.' extension required');
        }

        $check('app-key', filled(config('app.key')), 'APP_KEY must be configured');
        $check('storage', is_dir(storage_path()) && is_writable(storage_path()), 'storage directory must be writable');
        $check('bootstrap-cache', is_dir(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache')), 'bootstrap/cache must be writable');
        $check('assets', is_file(public_path('build/manifest.json')), 'Tracked production Vite manifest must exist');

        try {
            DB::connection()->getPdo();
            $check('database', true, 'Database connection successful');
            $check('migrations-table', Schema::hasTable('migrations'), 'Migration tracking table must exist for an upgrade');
        } catch (Throwable $exception) {
            $check('database', false, 'Database connection failed; inspect server connection settings');
        }

        if ($production) {
            $check('environment', app()->environment('production'), 'APP_ENV must be production');
            $check('debug', ! (bool) config('app.debug'), 'APP_DEBUG must be false');
            $check('https-url', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL must use HTTPS');
            $check('secure-cookie', config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true');
            $check('http-only-cookie', config('session.http_only') === true, 'Session cookies must be HttpOnly');
            $check('session-encryption', config('session.encrypt') === true, 'SESSION_ENCRYPT must be true');
        }

        $ok = ! in_array(false, array_column($checks, 'passed'), true);
        if ($this->option('json')) {
            $this->line((string) json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $item) {
                $this->{$item['passed'] ? 'info' : 'error'}(($item['passed'] ? 'PASS ' : 'FAIL ').$item['check'].' — '.$item['detail']);
            }
            $this->{$ok ? 'info' : 'error'}($ok ? 'Preflight passed.' : 'Preflight failed; do not deploy until corrected.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
