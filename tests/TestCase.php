<?php

namespace Tests;

use App\Models\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The settings request-memo is a static (per PHP process); PHPUnit runs
        // every test in one process, so flush it or tests read each other's DBs.
        Settings::flushMemo();

        // The suite runs with KHQR_PAY_ENABLED=true. A test that reaches a KHQR
        // path without Http::fake() must fail loudly rather than spend a real,
        // metered Bakong request against whatever profile it happens to resolve.
        Http::preventStrayRequests();
    }
}
