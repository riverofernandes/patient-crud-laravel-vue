<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    use RefreshDatabase;

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();

        // Captures what the real logger receives (context from Log::withContext included).
        $this->logs = new TestHandler();
        $driver = Log::driver();
        $this->assertInstanceOf(Logger::class, $driver);
        $monolog = $driver->getLogger();
        $this->assertInstanceOf(MonologLogger::class, $monolog);
        $monolog->pushHandler($this->logs);
    }

    /**
     * @return array<int, LogRecord>
     */
    private function accessLines(): array
    {
        return array_values(array_filter($this->logs->getRecords(), fn (LogRecord $record) => $record->message === 'http.request'));
    }

    public function test_generates_a_uuid_when_absent(): void
    {
        $id = $this->getJson('/api/patients')->assertOk()->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $id));
    }

    public function test_reuses_a_valid_incoming_id(): void
    {
        foreach (['abc-123_X.y', (string) Str::uuid(), str_repeat('a', 64)] as $incoming) {
            $this->getJson('/api/patients', ['X-Request-Id' => $incoming])->assertHeader('X-Request-Id', $incoming);
        }
    }

    public function test_replaces_an_unsafe_incoming_id(): void
    {
        foreach ([str_repeat('a', 65), 'id com espaço', 'x<script>', 'a/b', ''] as $incoming) {
            $id = (string) $this->getJson('/api/patients', ['X-Request-Id' => $incoming])->headers->get('X-Request-Id');

            $this->assertNotSame($incoming, $id);
            $this->assertTrue(Str::isUuid($id));
        }
    }

    public function test_error_and_preflight_responses_carry_the_header(): void
    {
        $this->getJson('/api/patients/999999')->assertNotFound()->assertHeader('X-Request-Id');
        $this->getJson('/api/nao-existe')->assertNotFound()->assertHeader('X-Request-Id');
        $this->patchJson('/api/patients')->assertStatus(405)->assertHeader('X-Request-Id');
        $this->postJson('/api/patients', [])->assertUnprocessable()->assertHeader('X-Request-Id');

        $this->call('OPTIONS', '/api/patients', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('X-Request-Id');

        // Exposed to the SPA via CORS.
        $this->getJson('/api/patients', ['Origin' => 'http://localhost:5173'])
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition, X-Request-Id');
    }

    public function test_one_access_line_per_request_with_route_pattern_and_request_id(): void
    {
        $patient = Patient::factory()->create();

        $this->getJson("/api/patients/{$patient->id}", ['X-Request-Id' => 'req-1'])->assertOk();
        $this->getJson('/api/nao-existe?cpf=62518953060', ['X-Request-Id' => 'req-2'])->assertNotFound();

        $lines = $this->accessLines();
        $this->assertCount(2, $lines);

        $this->assertSame('GET', $lines[0]->context['method']);
        $this->assertSame('api/patients/{patient}', $lines[0]->context['route']); // pattern, not the id
        $this->assertSame(200, $lines[0]->context['status']);
        $this->assertIsFloat($lines[0]->context['duration_ms']);
        $this->assertSame('req-1', $lines[0]->context['request_id']);

        $this->assertSame('api/nao-existe', $lines[1]->context['route']); // unmatched: plain path
        $this->assertSame(404, $lines[1]->context['status']);
        $this->assertSame('req-2', $lines[1]->context['request_id']);
    }

    public function test_query_string_and_body_never_reach_the_log(): void
    {
        $cpf = '62518953060';

        $this->getJson("/api/patients?search={$cpf}")->assertOk();
        $this->get('/api/patients/export?search=625.189.530-60&name=Maria')->assertOk();
        $this->postJson('/api/patients', ['name' => 'Maria Sigilosa', 'cpf' => $cpf])->assertUnprocessable();

        $lines = $this->accessLines();
        $this->assertCount(3, $lines);
        $this->assertSame(['api/patients', 'api/patients/export', 'api/patients'], array_map(fn (LogRecord $r) => $r->context['route'], $lines));

        $everything = json_encode(array_map(fn (LogRecord $record) => $record->toArray(), $this->logs->getRecords()));
        $this->assertIsString($everything);
        foreach ([$cpf, '625.189.530-60', 'Maria', 'search'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
    }

    public function test_request_id_is_in_the_context_of_application_logs(): void
    {
        config(['services.viacep.url' => 'https://viacep.test']);
        Http::fake(['viacep.test/*' => Http::response('', 500)]);

        $this->getJson('/api/cep/01310100', ['X-Request-Id' => 'req-cep'])->assertStatus(503);

        $warning = collect($this->logs->getRecords())->first(fn (LogRecord $record) => $record->message === 'CEP lookup failed');
        $this->assertInstanceOf(LogRecord::class, $warning);
        $this->assertSame('req-cep', $warning->context['request_id']);
        $this->assertStringNotContainsString('01310100', (string) json_encode($warning->toArray()));
    }

    public function test_production_json_channel_writes_json_to_stderr(): void
    {
        $channel = Log::channel('json');
        $this->assertInstanceOf(Logger::class, $channel);
        $monolog = $channel->getLogger();
        $this->assertInstanceOf(MonologLogger::class, $monolog);
        $handler = $monolog->getHandlers()[0] ?? null;

        $this->assertInstanceOf(StreamHandler::class, $handler);
        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
        $this->assertSame('php://stderr', $handler->getUrl());
    }
}
