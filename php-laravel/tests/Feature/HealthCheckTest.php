<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_ok_when_the_database_is_reachable(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_it_returns_503_without_internals_when_the_database_is_down(): void
    {
        Config::set('database.connections.broken', [
            'driver' => 'sqlite',
            'database' => '/nonexistent/path/database.sqlite',
        ]);
        Config::set('database.default', 'broken');
        DB::purge('broken');

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'unavailable',
                'message' => 'Banco de dados indisponível.',
            ]);
    }

    public function test_it_is_not_rate_limited(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1));

        $this->getJson('/api/patients')->assertOk();
        $this->getJson('/api/patients')->assertStatus(429);

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/health')->assertOk();
        }
    }

    public function test_routing_errors_are_json_even_without_accept_header(): void
    {
        $this->post('/api/health')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }
}
