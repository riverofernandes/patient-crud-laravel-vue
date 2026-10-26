<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_a_deleted_patient(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->postJson("/api/patients/{$patient->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.cpf', $patient->cpf)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted($patient);
        $this->getJson("/api/patients/{$patient->id}")->assertOk();
        $this->getJson('/api/patients?trashed=only')->assertJsonPath('meta.total', 0);
    }

    public function test_restoring_an_active_patient_returns_404(): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/restore")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Paciente não encontrado.']);
    }

    public function test_restoring_a_missing_patient_returns_404(): void
    {
        $this->postJson('/api/patients/999/restore')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Paciente não encontrado.']);
    }

    public function test_restore_conflicts_when_another_active_patient_uses_the_cpf(): void
    {
        $deleted = Patient::factory()->create(['cpf' => '62518953060']);
        $deleted->delete();
        Patient::factory()->create(['cpf' => '62518953060']);

        $this->postJson("/api/patients/{$deleted->id}/restore")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Já existe um paciente ativo com este CPF.']);

        $this->assertSoftDeleted($deleted);
    }

    public function test_another_deleted_patient_with_the_same_cpf_does_not_block_restore(): void
    {
        Patient::factory()->create(['cpf' => '62518953060'])->delete();
        $deleted = Patient::factory()->create(['cpf' => '62518953060']);
        $deleted->delete();

        $this->postJson("/api/patients/{$deleted->id}/restore")->assertOk();
    }

    public function test_cpf_reuse_flow_delete_recreate_then_restore_conflicts(): void
    {
        $payload = [
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '356880199',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '11928535718',
        ];

        $original = $this->postJson('/api/patients', $payload)->assertCreated()->json('data.id');
        $this->deleteJson("/api/patients/{$original}")->assertNoContent();

        $this->getJson('/api/patients/cpf-availability?cpf=62518953060')->assertExactJson(['available' => true]);
        $replacement = $this->postJson('/api/patients', $payload)->assertCreated()->json('data.id');

        $this->postJson("/api/patients/{$original}/restore")->assertStatus(409);

        // Once the replacement is deleted, the original can come back.
        $this->deleteJson("/api/patients/{$replacement}")->assertNoContent();
        $this->postJson("/api/patients/{$original}/restore")->assertOk()->assertJsonPath('data.cpf', '62518953060');
    }
}
