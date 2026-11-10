<?php

// Read effective Laravel configuration (including config:cache), without issuing SQL,
// sending mail, modifying .env, or launching a worker. Do not print credentials.
$stage = 'task-configuration';
try {
    $settings = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
    $root = realpath($settings['AppPath']);
    if ($root !== realpath(dirname(__DIR__, 2))) {
        throw new RuntimeException('Checkout mismatch.');
    }
    $stage = 'laravel-bootstrap';
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $checkDatabase = function (?string $connection) use ($app, $settings): void {
        // Laravel resolves DB_URL here; this creates a lazy connection, not a SQL query.
        $db = $app['db']->connection($connection);
        if ($db->getConfig('driver') !== 'mysql'
            || $db->getConfig('database') !== $settings['ExpectedDatabase']
            || $db->getConfig('host') !== $settings['ExpectedDatabaseHost']) {
            throw new RuntimeException('Database target mismatch.');
        }
    };
    $stage = 'application-database';
    $checkDatabase(null);
    $stage = 'private-test-mode';
    $private = (bool) config('private_test.enabled');
    if (($settings['EnvironmentKind'] === 'PrivateTest') !== $private
        || ($private && !str_ends_with($settings['ExpectedDatabase'], '_test'))) {
        throw new RuntimeException('Private-test isolation mismatch.');
    }
    $stage = 'queue-settings';
    if (config('queue.default') !== 'database'
        || config('queue.connections.database.driver') !== 'database'
        || config('queue.connections.database.queue') !== 'default'
        || (int) config('queue.connections.database.retry_after') <= 60) {
        throw new RuntimeException('Queue must use database/default with retry_after greater than 60.');
    }
    $stage = 'queue-database';
    $checkDatabase(config('queue.connections.database.connection'));
    $stage = 'cache-settings';
    $cache = config('cache.stores.'.config('cache.default'));
    if (($cache['driver'] ?? null) === 'database') {
        $stage = 'cache-database';
        $checkDatabase($cache['connection'] ?? null);
        $checkDatabase($cache['lock_connection'] ?? $cache['connection'] ?? null);
        if (!is_string(config('cache.prefix')) || config('cache.prefix') === '') {
            throw new RuntimeException('A nonempty isolated cache prefix is required.');
        }
    } elseif (($cache['driver'] ?? null) === 'file') {
        $stage = 'file-cache';
        $storage = realpath($root.'/storage').DIRECTORY_SEPARATOR;
        $path = realpath($cache['path']);
        if ($path === false || !str_starts_with(strtolower($path).DIRECTORY_SEPARATOR, strtolower($storage))) {
            throw new RuntimeException('File cache must be inside this checkout storage directory.');
        }
    } else {
        throw new RuntimeException('queue:restart requires this checkout file cache or isolated database cache.');
    }
    echo "Queue environment matches the approved target.\n";
} catch (Throwable $exception) {
    // Avoid leaking database URLs/passwords from framework exceptions.
    fwrite(STDERR, "Queue environment check failed [$stage]; inspect effective configuration privately.\n");
    exit(1);
}
