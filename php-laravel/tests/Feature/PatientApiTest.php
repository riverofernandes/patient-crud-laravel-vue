<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Rules\Cpf;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '35.688.019-x',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '(11) 92853-5718',
            'email' => 'maria@example.com',
            'description' => 'Paciente com miopia.',
        ], $overrides);
    }

    public function test_it_lists_patients_paginated_and_ordered_by_name(): void
    {
        Patient::factory()->create(['name' => 'Zeca Pagodinho']);
        Patient::factory()->create(['name' => 'Ana Maria']);
        Patient::factory()->count(13)->create(['name' => 'Bruno Souza']);

        $response = $this->getJson('/api/patients');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.name', 'Ana Maria')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'cpf', 'rg', 'birth_date', 'sex', 'phone', 'email', 'description', 'created_at', 'updated_at', 'age', 'deleted_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);
    }

    public function test_per_page_is_clamped(): void
    {
        Patient::factory()->count(3)->create();

        $this->getJson('/api/patients?per_page=1000')->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/patients?per_page=0')->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/patients?per_page=-5')->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/patients?per_page=abc')->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/patients?per_page=1')->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/patients?per_page=2')->assertJsonCount(2, 'data');
    }

    public function test_it_searches_by_partial_name(): void
    {
        Patient::factory()->create(['name' => 'Alfredo Nunes']);
        Patient::factory()->create(['name' => 'Beatriz Lima']);

        $this->getJson('/api/patients?search=fred')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alfredo Nunes');
    }

    public function test_it_searches_by_masked_cpf(): void
    {
        Patient::factory()->create(['cpf' => '62518953060', 'name' => 'Carlos Alberto']);
        Patient::factory()->create(['cpf' => '52998224725', 'name' => 'Daniela Prado']);

        $this->getJson('/api/patients?search='.urlencode('625.189.530-60'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Carlos Alberto');

        $this->getJson('/api/patients?search=529.982')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Daniela Prado');
    }

    public function test_name_search_with_digits_does_not_match_cpfs(): void
    {
        Patient::factory()->create(['name' => 'Equipe E2E', 'cpf' => '62518953060']);
        Patient::factory()->create(['name' => 'Fulano de Tal', 'cpf' => '52998224725']);

        $this->getJson('/api/patients?search=E2E')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Equipe E2E');
    }

    public function test_search_escapes_like_wildcards(): void
    {
        Patient::factory()->create(['name' => 'Ana 100% Silva']);
        Patient::factory()->create(['name' => 'Bruno_Costa']);
        Patient::factory()->create(['name' => 'Carla Mendes']);

        $this->getJson('/api/patients?search='.urlencode('%'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana 100% Silva');

        $this->getJson('/api/patients?search=_')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bruno_Costa');
    }

    public function test_search_does_not_include_soft_deleted_patients(): void
    {
        Patient::factory()->create(['name' => 'Eduardo Ativo']);
        Patient::factory()->create(['name' => 'Eduardo Removido'])->delete();

        $this->getJson('/api/patients?search=Eduardo')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Eduardo Ativo');
    }

    public function test_it_shows_a_patient(): void
    {
        $patient = Patient::factory()->create(['birth_date' => '1980-01-02']);

        $this->getJson("/api/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.cpf', $patient->cpf)
            ->assertJsonPath('data.birth_date', '1980-01-02');
    }

    public function test_show_returns_404_for_missing_patient(): void
    {
        $this->getJson('/api/patients/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Paciente não encontrado.']);
    }

    public function test_non_numeric_ids_return_404_without_touching_rows(): void
    {
        $patient = Patient::factory()->create();

        foreach (["{$patient->id}abc", "{$patient->id}e0", '%20'.$patient->id, '0'.$patient->id] as $id) {
            $this->getJson("/api/patients/{$id}")->assertNotFound();
            $this->deleteJson("/api/patients/{$id}")->assertNotFound();
            $this->postJson("/api/patients/{$id}/restore")->assertNotFound();
        }

        $this->assertNotSoftDeleted($patient);
    }

    public function test_array_query_params_do_not_break_the_listing(): void
    {
        Patient::factory()->create();

        $this->getJson('/api/patients?search[]=a&sort[]=name&direction[]=desc&trashed[]=only&per_page[]=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_unknown_api_route_returns_generic_404(): void
    {
        $this->getJson('/api/unknown')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_rate_limit_returns_json_in_portuguese(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1));

        $this->getJson('/api/patients')->assertOk();
        $this->getJson('/api/patients')
            ->assertStatus(429)
            ->assertExactJson(['message' => 'Muitas requisições. Tente novamente em instantes.'])
            ->assertHeader('Retry-After');
    }

    public function test_it_creates_a_patient_storing_only_digits(): void
    {
        $response = $this->postJson('/api/patients', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria da Silva')
            ->assertJsonPath('data.cpf', '62518953060')
            ->assertJsonPath('data.rg', '35688019X')
            ->assertJsonPath('data.phone', '11928535718')
            ->assertJsonPath('data.birth_date', '1996-07-20');

        $this->assertDatabaseHas('patients', [
            'cpf' => '62518953060',
            'rg' => '35688019X',
            'phone' => '11928535718',
        ]);
    }

    public function test_optional_fields_may_be_omitted(): void
    {
        $payload = $this->validPayload();
        unset($payload['email'], $payload['description']);

        $this->postJson('/api/patients', $payload)
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.description', null);
    }

    public function test_create_requires_mandatory_fields_with_portuguese_messages(): void
    {
        $this->postJson('/api/patients', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'cpf', 'rg', 'birth_date', 'sex', 'phone'])
            ->assertJsonMissingValidationErrors(['email', 'description'])
            ->assertJsonPath('errors.name.0', 'O campo nome completo é obrigatório.')
            ->assertJsonPath('errors.birth_date.0', 'O campo data de nascimento é obrigatório.');
    }

    public function test_validation_errors_are_json_even_without_accept_header(): void
    {
        $response = $this->post('/api/patients', []);

        $response->assertUnprocessable();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $response->assertJsonPath('errors.name.0', 'O campo nome completo é obrigatório.');
    }

    public function test_create_rejects_invalid_cpf(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['cpf' => '123.456.789-00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'O CPF informado é inválido.');
    }

    public function test_create_rejects_duplicate_cpf(): void
    {
        Patient::factory()->create(['cpf' => '62518953060']);

        $this->postJson('/api/patients', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'Já existe um paciente cadastrado com este CPF.');
    }

    public function test_create_rejects_invalid_email(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'O campo e-mail deve ser um endereço de e-mail válido.');
    }

    public function test_create_rejects_future_birth_date(): void
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $this->postJson('/api/patients', $this->validPayload(['birth_date' => $tomorrow]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.birth_date.0', 'O campo data de nascimento não pode ser uma data futura.');
    }

    public function test_invalid_birth_date_reports_a_single_error(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['birth_date' => '20/07/1996']))
            ->assertUnprocessable()
            ->assertJsonCount(1, 'errors.birth_date')
            ->assertJsonPath('errors.birth_date.0', 'O campo data de nascimento deve ser uma data válida no formato AAAA-MM-DD.');
    }

    public function test_create_rejects_invalid_sex(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['sex' => 'X']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sex']);
    }

    public function test_create_rejects_invalid_phone_and_short_name(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['phone' => '1234', 'name' => 'Al']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'O campo celular deve ter entre 10 e 11 dígitos.')
            ->assertJsonPath('errors.name.0', 'O campo nome completo deve ter pelo menos 3 caracteres.');
    }

    public function test_it_updates_a_patient_keeping_the_same_cpf(): void
    {
        $patient = Patient::factory()->create(['cpf' => '62518953060']);

        $this->putJson("/api/patients/{$patient->id}", $this->validPayload(['name' => 'Maria Atualizada']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Atualizada')
            ->assertJsonPath('data.cpf', '62518953060');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'name' => 'Maria Atualizada']);
    }

    public function test_update_clears_omitted_optional_fields(): void
    {
        $patient = Patient::factory()->create(['email' => 'old@example.com', 'description' => 'Antiga']);

        $payload = $this->validPayload(['cpf' => $patient->cpf]);
        unset($payload['email'], $payload['description']);

        $this->putJson("/api/patients/{$patient->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.description', null);
    }

    public function test_update_rejects_cpf_of_another_patient(): void
    {
        Patient::factory()->create(['cpf' => '62518953060']);
        $patient = Patient::factory()->create(['cpf' => '52998224725']);

        $this->putJson("/api/patients/{$patient->id}", $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);
    }

    public function test_update_returns_404_for_missing_patient(): void
    {
        $this->putJson('/api/patients/999', $this->validPayload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Paciente não encontrado.');
    }

    public function test_it_soft_deletes_a_patient(): void
    {
        $patient = Patient::factory()->create();

        $this->deleteJson("/api/patients/{$patient->id}")->assertNoContent();

        $this->assertSoftDeleted($patient);
        $this->getJson("/api/patients/{$patient->id}")->assertNotFound();
        $this->getJson('/api/patients')->assertJsonPath('meta.total', 0);
        $this->deleteJson("/api/patients/{$patient->id}")->assertNotFound();
    }

    public function test_cpf_of_a_deleted_patient_can_be_reused(): void
    {
        Patient::factory()->create(['cpf' => '62518953060'])->delete();

        $this->postJson('/api/patients', $this->validPayload())->assertCreated();

        $this->assertSame(2, Patient::withTrashed()->where('cpf', '62518953060')->count());
    }

    public function test_factory_generates_valid_cpfs(): void
    {
        Patient::factory()->count(50)->make()->each(
            fn (Patient $patient) => $this->assertTrue(Cpf::isValid($patient->cpf), "Invalid CPF {$patient->cpf}")
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(30, Patient::count());
    }
}
