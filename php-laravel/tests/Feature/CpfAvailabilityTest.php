<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpfAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const TAKEN = ['available' => false, 'message' => 'Já existe um paciente cadastrado com este CPF.'];

    public function test_unused_cpf_is_available(): void
    {
        Patient::factory()->create(['cpf' => '52998224725']);

        $this->getJson('/api/patients/cpf-availability?cpf=62518953060')
            ->assertOk()
            ->assertExactJson(['available' => true]);
    }

    public function test_cpf_of_an_active_patient_is_taken_with_or_without_mask(): void
    {
        Patient::factory()->create(['cpf' => '62518953060']);

        $this->getJson('/api/patients/cpf-availability?cpf=62518953060')->assertOk()->assertExactJson(self::TAKEN);
        $this->getJson('/api/patients/cpf-availability?cpf='.urlencode('625.189.530-60'))->assertOk()->assertExactJson(self::TAKEN);
    }

    public function test_ignore_excludes_the_patient_being_edited(): void
    {
        $patient = Patient::factory()->create(['cpf' => '62518953060']);
        $other = Patient::factory()->create();

        $this->getJson("/api/patients/cpf-availability?cpf=62518953060&ignore={$patient->id}")
            ->assertExactJson(['available' => true]);
        $this->getJson("/api/patients/cpf-availability?cpf=62518953060&ignore={$other->id}")
            ->assertExactJson(self::TAKEN);
    }

    public function test_soft_deleted_patients_are_ignored(): void
    {
        Patient::factory()->create(['cpf' => '62518953060'])->delete();

        $this->getJson('/api/patients/cpf-availability?cpf=62518953060')->assertExactJson(['available' => true]);
    }

    public function test_invalid_cpf_returns_422_with_the_create_messages(): void
    {
        $this->getJson('/api/patients/cpf-availability?cpf='.urlencode('123.456.789-00'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'O CPF informado é inválido.');

        $this->getJson('/api/patients/cpf-availability?cpf=11111111111')
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'O CPF informado é inválido.');

        $this->getJson('/api/patients/cpf-availability')
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'O campo CPF é obrigatório.');

        $this->getJson('/api/patients/cpf-availability?cpf[]=62518953060')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);
    }

    public function test_invalid_ignore_returns_422(): void
    {
        $this->getJson('/api/patients/cpf-availability?cpf=62518953060&ignore=abc')
            ->assertUnprocessable()
            ->assertJsonPath('errors.ignore.0', 'O campo paciente ignorado deve ser um número inteiro.');
    }

    public function test_route_does_not_clash_with_patient_show(): void
    {
        $this->getJson('/api/patients/cpf-availability?cpf=62518953060')
            ->assertOk()
            ->assertJsonMissingPath('data');
    }
}
