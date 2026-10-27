<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CepLookupTest extends TestCase
{
    private const VIACEP = 'https://viacep.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.viacep.url' => self::VIACEP]);
        Cache::flush();
    }

    /**
     * @return array<string, string>
     */
    private function viaCepBody(): array
    {
        return [
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'complemento' => 'de 1047 a 1865 - lado ímpar',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'ibge' => '3550308',
        ];
    }

    public function test_found_cep_is_mapped_and_cached(): void
    {
        Http::fake([self::VIACEP.'/*' => Http::response($this->viaCepBody())]);

        $expected = ['data' => [
            'cep' => '01310100',
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]];

        $this->getJson('/api/cep/01310-100')->assertOk()->assertExactJson($expected);
        // Cache hit (masked and unmasked are the same key): ViaCEP is called only once.
        $this->getJson('/api/cep/01310100')->assertOk()->assertExactJson($expected);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::VIACEP.'/ws/01310100/json/');
    }

    public function test_unknown_cep_is_404_and_cached(): void
    {
        Http::fake([self::VIACEP.'/*' => Http::response(['erro' => true])]);

        $this->getJson('/api/cep/99999999')->assertNotFound()->assertExactJson(['message' => 'CEP não encontrado.']);
        $this->getJson('/api/cep/99999-999')->assertNotFound();

        Http::assertSentCount(1);
    }

    public function test_legacy_string_error_flag_is_also_not_found(): void
    {
        Http::fake([self::VIACEP.'/*' => Http::response(['erro' => 'true'])]);

        $this->getJson('/api/cep/99999998')->assertNotFound()->assertJsonPath('message', 'CEP não encontrado.');
    }

    public function test_invalid_format_is_422_without_calling_viacep(): void
    {
        Http::fake();

        foreach (['123', '0131010a', '013101000', 'abcdefgh'] as $cep) {
            $this->getJson("/api/cep/{$cep}")
                ->assertUnprocessable()
                ->assertExactJson(['message' => 'O CEP deve ter 8 dígitos.', 'errors' => ['cep' => ['O CEP deve ter 8 dígitos.']]]);
        }

        Http::assertNothingSent();
    }

    public function test_server_error_is_503_and_not_cached(): void
    {
        Http::fake([self::VIACEP.'/*' => Http::response('Bad gateway', 502)]);

        $this->getJson('/api/cep/01310100')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Serviço de CEP indisponível. Preencha o endereço manualmente.']);
        $this->getJson('/api/cep/01310100')->assertStatus(503);

        Http::assertSentCount(2);
    }

    public function test_timeout_is_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->getJson('/api/cep/01310100')->assertStatus(503);
    }

    public function test_unexpected_body_is_503(): void
    {
        Http::fake([self::VIACEP.'/*' => Http::sequence()
            ->push('<html>maintenance</html>', 200, ['Content-Type' => 'text/html'])
            ->push(['cep' => '01310-100'])]);

        $this->getJson('/api/cep/01310100')->assertStatus(503);
        $this->getJson('/api/cep/01310100')->assertStatus(503);
    }
}
