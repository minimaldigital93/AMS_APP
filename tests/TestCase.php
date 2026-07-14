<?php

namespace Tests;

use App\Models\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The settings request-memo is a static (per PHP process); PHPUnit runs
        // every test in one process, so flush it or tests read each other's DBs.
        Settings::flushMemo();
    }
}
