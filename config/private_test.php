<?php

return [
    'enabled' => env('PRIVATE_TEST_ENABLED', false),
    'emails' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', env('PRIVATE_TEST_EMAILS', '')),
    ))),
    'networks' => array_values(array_filter(array_map('trim', explode(',', env('PRIVATE_TEST_NETWORKS', '127.0.0.1/32,::1/128'))))),
];
