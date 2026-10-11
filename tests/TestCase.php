<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function actingAs($user, $guard = null)
    {
        $this->withSession(['auth_version' => $user->session_version ?? 0]);

        return parent::actingAs($user, $guard);
    }
}
