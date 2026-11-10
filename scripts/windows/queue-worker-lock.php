<?php

// PHP auto_prepend_file for artisan queue:work only. The handle remains owned by
// PHP until process exit, even if the PowerShell supervisor dies or is replaced.
$laundryQueueState = dirname(__DIR__, 2).'/storage/framework/laundry-queue';
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'queue:work') {
    fwrite(STDERR, "This prepend file is only for the managed queue worker.\n");
    exit(64);
}
$laundryQueueLock = fopen($laundryQueueState.'/worker.lock', 'c+');
if ($laundryQueueLock === false || !flock($laundryQueueLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another managed worker owns the lock; no worker started.\n");
    exit(75);
}
if (is_file($laundryQueueState.'/drain.request')) {
    exit(0);
}
$laundryQueueIdentity = json_encode([
    'pid' => getmypid(),
    'generation' => bin2hex(random_bytes(16)),
    'startedUtc' => gmdate('c'),
], JSON_THROW_ON_ERROR);
if (file_put_contents($laundryQueueState.'/worker.json', $laundryQueueIdentity) === false) {
    fwrite(STDERR, "Cannot write worker identity; no worker started.\n");
    exit(74);
}
// Do not close $laundryQueueLock. artisan runs next in this same PHP process.
