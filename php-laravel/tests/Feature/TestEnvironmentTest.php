<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * phpunit.xml pins the settings the suite depends on, so a developer's .env or shell cannot break it.
 */
class TestEnvironmentTest extends TestCase
{
    public function test_cors_origins_and_cpf_hash_key_are_pinned(): void
    {
        $this->assertSame(['http://localhost:5173', 'http://127.0.0.1:5173'], config('cors.allowed_origins'));
        $this->assertEmpty(config('patients.cpf_hash_key'));
    }
}
