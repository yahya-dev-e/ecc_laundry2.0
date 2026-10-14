<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReadinessCheck extends Command
{
    protected $signature = 'laundry:readiness';

    protected $description = 'Read-only production checks; never prints passwords, keys or student records';

    public function handle(): int
    {
        $failed = 0;
        $check = function (bool $ok, string $label) use (&$failed) {
            $this->line(($ok ? 'PASS ' : 'FAIL ').$label);
            $failed += (int) ! $ok;
        };
        $check(app()->environment('production'), 'APP_ENV is production');
        $check(! config('app.debug'), 'Debug details are disabled');
        $check((bool) config('app.key'), 'Application key is configured');
        $check(parse_url(config('app.url'), PHP_URL_SCHEME) === 'https', 'Canonical application URL uses HTTPS');
        $check((bool) config('session.secure'), 'Session cookies require HTTPS');
        $check(! config('private_test.enabled'), 'Private tester restriction is disabled for a general release');
        $check(! in_array(config('mail.default'), ['log', 'array', null], true), 'A delivery mailer is configured (delivery still needs a real test)');
        $check(! in_array(config('queue.default'), ['sync', 'null', null], true), 'Background work uses a persistent queue');
        $check(! file_exists(public_path('hot')), 'No development Vite hot file is present');
        $manifest = public_path('build/manifest.json');
        $assets = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : null;
        $validAssets = is_array($assets);
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            $validAssets = $validAssets && isset($assets[$entry]['file']) && is_file(public_path('build/'.$assets[$entry]['file']));
        }
        $check($validAssets, 'Built CSS and JavaScript exist');
        try {
            DB::connection()->getPdo();
            $check(true, 'Database connection works');
            foreach (['users', 'machines', 'reservations', 'transactions', 'password_reset_links', 'sessions', 'jobs', 'failed_jobs', 'cache'] as $table) {
                $check(Schema::hasTable($table), 'Required table: '.$table);
            }
            if (Schema::hasTable('users')) {
                $role = collect(Schema::getColumns('users'))->firstWhere('name', 'role');
                $supportsRoles = false;
                if ($role) {
                    if ($role['type_name'] === 'enum') {
                        $supportsRoles = str_contains($role['type'], "'student'") && str_contains($role['type'], "'admin'");
                    } elseif (in_array($role['type_name'], ['varchar', 'char', 'text'], true)) {
                        preg_match('/\((\d+)\)/', $role['type'], $length);
                        $supportsRoles = (int) ($length[1] ?? 255) >= strlen('student');
                    }
                }
                $check($supportsRoles, 'Account role column supports student and admin');
                foreach (['student_id', 'room_number'] as $name) {
                    $column = collect(Schema::getColumns('users'))->firstWhere('name', $name);
                    $check($column && $column['nullable'], 'Optional profile column accepts NULL: '.$name);
                }
            }
            if (Schema::hasTable('migrations')) {
                $applied = DB::table('migrations')->pluck('migration')->all();
                $pending = array_filter(glob(database_path('migrations/*.php')), fn ($file) => ! in_array(basename($file, '.php'), $applied, true));
                $check(! $pending, 'All repository migrations have run');
            } else {
                $check(false, 'Migration history exists');
            }
        } catch (\Throwable $e) {
            $check(false, 'Database/schema inspection failed; inspect the server privately for details');
        }
        $this->warn('MANUAL: rehearse backup/restore and the production upgrade; verify scheduler and queue worker, real email delivery, HTTPS routing and a physical phone.');
        $this->line('This check changes no data. Passing it is not a production approval.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
