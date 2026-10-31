<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Support\EncryptedFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Erros gerais" of the API contract: malformed JSON, 405, malformed ids, `address` as text, photo_url in the trash.
 */
class GeneralErrorsTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'http://localhost:5173';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '35.688.019-X',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '(11) 92853-5718',
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => self::ORIGIN];
    }

    // --- Malformed JSON (400) ---

    public function test_malformed_json_is_a_400_with_request_id_and_cors(): void
    {
        $patient = Patient::factory()->create(['name' => 'Nome Original']);

        $this->call('PATCH', "/api/patients/{$patient->id}", [], [], [], $this->jsonHeaders(), '{"name":"Outro",}')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'JSON inválido.'])
            ->assertHeader('X-Request-Id')
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);

        $this->assertSame('Nome Original', $patient->refresh()->name);
    }

    public function test_malformed_json_on_create_is_a_400_not_a_validation_error(): void
    {
        $this->call('POST', '/api/patients', [], [], [], $this->jsonHeaders(), '{name: "x"}')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'JSON inválido.']);
    }

    public function test_variant_json_content_types_are_checked_too(): void
    {
        $headers = ['CONTENT_TYPE' => 'application/merge-patch+json; charset=utf-8'] + $this->jsonHeaders();

        $this->call('POST', '/api/patients', [], [], [], $headers, '[')->assertStatus(400);
    }

    public function test_empty_body_is_still_allowed(): void
    {
        $patient = Patient::factory()->create();

        $this->call('POST', "/api/patients/{$patient->id}/restore", [], [], [], $this->jsonHeaders(), '')
            ->assertNotFound(); // reached the controller (active patient), no 400

        $this->call('PATCH', "/api/patients/{$patient->id}", [], [], [], $this->jsonHeaders(), "  \n")
            ->assertOk();
    }

    public function test_non_json_bodies_are_not_parsed(): void
    {
        $this->call('POST', '/api/patients', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], '{oops')
            ->assertUnprocessable();
    }

    // --- 405 ---

    public function test_method_not_allowed_is_json_in_portuguese_with_allow_header(): void
    {
        $response = $this->deleteJson('/api/patients', [], ['Origin' => self::ORIGIN])
            ->assertStatus(405)
            ->assertExactJson(['message' => 'Método não permitido.'])
            ->assertHeader('X-Request-Id')
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);

        $allow = (string) $response->headers->get('Allow');
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('POST', $allow);
    }

    // --- Malformed ids: resource-specific 404 ---

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function malformedIds(): array
    {
        return [
            'patient abc' => ['GET', '/api/patients/abc', 'Paciente não encontrado.'],
            'patient zero' => ['GET', '/api/patients/0', 'Paciente não encontrado.'],
            'patient leading zero' => ['PUT', '/api/patients/01', 'Paciente não encontrado.'],
            'patient patch' => ['PATCH', '/api/patients/1a', 'Paciente não encontrado.'],
            'patient nested' => ['GET', '/api/patients/abc/consultations', 'Paciente não encontrado.'],
            'patient restore' => ['POST', '/api/patients/-1/restore', 'Paciente não encontrado.'],
            'consultation' => ['GET', '/api/consultations/abc', 'Atendimento não encontrado.'],
            'consultation status' => ['PATCH', '/api/consultations/0/status', 'Atendimento não encontrado.'],
            'attachment download' => ['GET', '/api/attachments/x/download', 'Anexo não encontrado.'],
            'attachment delete' => ['DELETE', '/api/attachments/1.5', 'Anexo não encontrado.'],
            'unknown sub-route stays generic' => ['GET', '/api/patients/1/nope', 'Recurso não encontrado.'],
            'unknown resource stays generic' => ['GET', '/api/doctors/abc', 'Recurso não encontrado.'],
        ];
    }

    #[DataProvider('malformedIds')]
    public function test_malformed_ids_return_the_resource_404_message(string $method, string $uri, string $message): void
    {
        $this->json($method, $uri)->assertNotFound()->assertExactJson(['message' => $message]);
    }

    // --- address as text ---

    /**
     * @return array<string, array{mixed}>
     */
    public static function textAddresses(): array
    {
        return [
            'empty string' => [''],
            'blank string' => ['   '],
            'text' => ['Rua A, 1'],
        ];
    }

    #[DataProvider('textAddresses')]
    public function test_address_as_text_is_rejected_on_create(mixed $address): void
    {
        $this->postJson('/api/patients', $this->payload(['address' => $address]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.address', ['O campo endereço deve ser um objeto.']);

        $this->assertDatabaseCount('patients', 0);
    }

    #[DataProvider('textAddresses')]
    public function test_address_as_text_is_rejected_on_put_and_patch_and_keeps_the_address(mixed $address): void
    {
        $id = $this->postJson('/api/patients', $this->payload(['address' => [
            'cep' => '01310-100', 'street' => 'Avenida Paulista', 'number' => '1578',
            'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP',
        ]]))->assertCreated()->json('data.id');

        $this->patchJson("/api/patients/{$id}", ['address' => $address])
            ->assertUnprocessable()
            ->assertJsonPath('errors.address', ['O campo endereço deve ser um objeto.']);

        $this->putJson("/api/patients/{$id}", $this->payload(['address' => $address]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.address', ['O campo endereço deve ser um objeto.']);

        $this->getJson("/api/patients/{$id}")->assertJsonPath('data.address.cep', '01310100');
    }

    public function test_form_encoded_address_text_is_rejected_and_keeps_the_address(): void
    {
        $id = $this->postJson('/api/patients', $this->payload(['address' => [
            'cep' => '01310-100', 'street' => 'Avenida Paulista', 'number' => '1578',
            'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP',
        ]]))->assertCreated()->json('data.id');

        // A form body cannot express null, so "address=" must not remove the address.
        foreach (['', 'Rua X'] as $address) {
            $this->patch("/api/patients/{$id}", ['address' => $address], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.address', ['O campo endereço deve ser um objeto.']);
        }

        // Without the key, a form PATCH still works and keeps the address.
        $this->patch("/api/patients/{$id}", ['name' => 'Maria Formulário'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.address.cep', '01310100');
    }

    public function test_only_null_removes_the_address_and_other_empty_strings_still_become_null(): void
    {
        $id = $this->postJson('/api/patients', $this->payload([
            'email' => 'maria@example.com',
            'address' => [
                'cep' => '01310100', 'street' => 'Avenida Paulista', 'number' => '1578',
                'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP', 'complement' => '',
            ],
        ]))->assertCreated()->assertJsonPath('data.address.complement', null)->json('data.id');

        $this->patchJson("/api/patients/{$id}", ['email' => '', 'address' => null])
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.address', null);
    }

    // --- photo_url in the trash ---

    public function test_trashed_patients_have_no_photo_url(): void
    {
        Storage::fake(EncryptedFiles::DISK);
        $patient = Patient::factory()->create();
        $this->post("/api/patients/{$patient->id}/photo", ['photo' => UploadedFile::fake()->image('p.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull($this->getJson("/api/patients/{$patient->id}")->json('data.photo_url'));

        $patient->delete();

        $this->getJson('/api/patients?trashed=only')
            ->assertJsonPath('data.0.id', $patient->id)
            ->assertJsonPath('data.0.photo_url', null);

        // Back from the trash, the photo is reachable again.
        $url = $this->postJson("/api/patients/{$patient->id}/restore")->assertOk()->json('data.photo_url');
        $this->assertIsString($url);
        $this->get($url)->assertOk();
    }

    // --- PUT and PATCH are separate routes ---

    public function test_put_and_patch_are_separate_routes(): void
    {
        $this->assertSame('PUT', app('router')->getRoutes()->getByName('patients.update')?->methods()[0]);
        $this->assertSame(['PATCH'], app('router')->getRoutes()->getByName('patients.partial-update')?->methods());

        $patient = Patient::factory()->create(['name' => 'Nome Original', 'email' => 'a@example.com']);

        $this->patchJson("/api/patients/{$patient->id}", ['name' => 'Nome Novo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Novo')
            ->assertJsonPath('data.email', 'a@example.com');

        $this->putJson("/api/patients/{$patient->id}", ['name' => 'Nome Novo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf', 'rg', 'birth_date', 'sex', 'phone']);
    }
}
