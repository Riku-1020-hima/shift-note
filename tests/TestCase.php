<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // .env で DEMO_MODE=true にしていても、テストは普通のモードから始める
        // (デモ版の動きを確かめるテストは、各テストの中でオンにする)
        config(['demo.enabled' => false]);
    }
}
