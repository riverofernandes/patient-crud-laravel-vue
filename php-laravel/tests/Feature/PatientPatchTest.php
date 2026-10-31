<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\PatientAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function address(): array
    {
        return [
            'cep' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1578',
            'complement' => 'Sala 12',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];
    }

    private function createPatient(): int
    {
        return $this->postJson('/api/patients', [
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '35.688.019-x',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '(11) 92853-5718',
            'email' => 'maria@example.com',
            'description' => 'Paciente com miopia.',
            'address' => $this->address(),
        ])->assertCreated()->json('data.id');
    }

    public function test_patch_updates_only_the_sent_fields(): void
    {
        $id = $this->createPatient();

        $this->patchJson("/api/patients/{$id}", ['name' => 'Maria da Silva Souza'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria da Silva Souza')
            ->assertJsonPath('data.cpf', '62518953060')
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.description', 'Paciente com miopia.')
            ->assertJsonPath('data.address.number', '1578');

        $audit = PatientAudit::query()->where('event', 'updated')->sole();
        $this->assertSame(['name'], array_keys($audit->changes));
    }

    public function test_patch_normalizes_and_validates_sent_fields(): void
    {
        $id = $this->createPatient();

        $this->patchJson("/api/patients/{$id}", ['phone' => '(21) 3333-4444', 'cpf' => '625.189.530-60'])
            ->assertOk()
            ->assertJsonPath('data.phone', '2133334444');

        $this->patchJson("/api/patients/{$id}", ['name' => '', 'email' => 'invalido'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email'])
            ->assertJsonMissingValidationErrors(['cpf', 'rg', 'birth_date', 'sex', 'phone']);

        $other = Patient::factory()->create();
        $this->patchJson("/api/patients/{$id}", ['cpf' => $other->cpf])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);
    }

    public function test_patch_can_clear_optional_fields_explicitly(): void
    {
        $id = $this->createPatient();

        $this->patchJson("/api/patients/{$id}", ['email' => null])
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.description', 'Paciente com miopia.');
    }

    public function test_patch_keeps_replaces_or_removes_the_address(): void
    {
        $id = $this->createPatient();

        $this->patchJson("/api/patients/{$id}", ['address' => ['number' => '10']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address.cep', 'address.street', 'address.city']);

        $this->patchJson("/api/patients/{$id}", ['address' => array_merge($this->address(), ['number' => '2000'])])
            ->assertOk()
            ->assertJsonPath('data.address.number', '2000');

        $this->patchJson("/api/patients/{$id}", ['address' => null])
            ->assertOk()
            ->assertJsonPath('data.address', null);
        $this->assertSame(0, PatientAddress::count());
    }

    public function test_empty_patch_is_a_no_op(): void
    {
        $id = $this->createPatient();

        $this->patchJson("/api/patients/{$id}", [])
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria da Silva')
            ->assertJsonPath('data.address.street', 'Avenida Paulista');

        $this->assertSame(0, PatientAudit::query()->where('event', 'updated')->count());
    }

    public function test_patch_returns_404_for_missing_or_trashed_patient(): void
    {
        $this->patchJson('/api/patients/999999', ['name' => 'Fulano'])->assertNotFound();

        $patient = Patient::factory()->create();
        $patient->delete();
        $this->patchJson("/api/patients/{$patient->id}", ['name' => 'Fulano'])->assertNotFound();
    }
}
