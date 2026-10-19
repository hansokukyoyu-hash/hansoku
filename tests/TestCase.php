<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テストから本物の SNS API に通信しない(偽の応答が無い通信は失敗させる)
        Http::preventStrayRequests();
    }
}
