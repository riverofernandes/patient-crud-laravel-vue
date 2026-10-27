<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAudit;
use App\Observers\PatientObserver;
use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Souza',
            'cpf' => '625.189.530-60',
            'rg' => '35.688.019-x',
            'birth_date' => '1990-05-10',
            'sex' => 'F',
            'phone' => '(11) 90000-0000',
            'email' => 'ana@example.com',
            'description' => null,
        ], $overrides);
    }

    private function createViaApi(): int
    {
        return $this->withHeaders(['User-Agent' => 'AuditTest/1.0'])
            ->postJson('/api/patients', $this->payload())
            ->assertCreated()
            ->json('data.id');
    }

    public function test_create_records_every_field_with_null_old_values(): void
    {
        $id = $this->createViaApi();

        $this->getJson("/api/patients/{$id}/audits")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'created')
            ->assertJsonPath('data.0.changes', [
                'name' => ['old' => null, 'new' => 'Ana Souza'],
                'cpf' => ['old' => null, 'new' => '62518953060'],
                'rg' => ['old' => null, 'new' => '35688019X'],
                'birth_date' => ['old' => null, 'new' => '1990-05-10'],
                'sex' => ['old' => null, 'new' => 'F'],
                'phone' => ['old' => null, 'new' => '11900000000'],
                'email' => ['old' => null, 'new' => 'ana@example.com'],
                'description' => ['old' => null, 'new' => null],
            ])
            ->assertJsonPath('data.0.ip', '127.0.0.1')
            ->assertJsonPath('data.0.user_agent', 'AuditTest/1.0')
            ->assertJsonPath('data.0.user_id', null)
            ->assertJsonStructure(['data' => [['id', 'event', 'changes', 'ip', 'user_agent', 'user_id', 'created_at']], 'links', 'meta']);
    }

    public function test_update_records_only_changed_fields_with_old_and_new_values(): void
    {
        $id = $this->createViaApi();

        $this->putJson("/api/patients/{$id}", $this->payload([
            'name' => 'Ana Souza Lima',
            'phone' => '11911111111',
            'birth_date' => '1990-05-11',
        ]))->assertOk();

        $this->getJson("/api/patients/{$id}/audits")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event', 'updated')
            ->assertJsonPath('data.0.changes', [
                'name' => ['old' => 'Ana Souza', 'new' => 'Ana Souza Lima'],
                'birth_date' => ['old' => '1990-05-10', 'new' => '1990-05-11'],
                'phone' => ['old' => '11900000000', 'new' => '11911111111'],
            ])
            ->assertJsonPath('data.1.event', 'created');
    }

    public function test_no_op_update_is_not_recorded(): void
    {
        $id = $this->createViaApi();

        // Same values, CPF/phone/RG with a different mask: nothing actually changes.
        $this->putJson("/api/patients/{$id}", $this->payload(['cpf' => '62518953060', 'phone' => '11900000000']))
            ->assertOk();

        $this->assertSame(['created'], PatientAudit::where('patient_id', $id)->pluck('event')->all());
    }

    public function test_delete_and_restore_are_recorded_with_empty_changes_object(): void
    {
        $id = $this->createViaApi();

        $this->deleteJson("/api/patients/{$id}")->assertNoContent();
        $this->postJson("/api/patients/{$id}/restore")->assertOk();

        $response = $this->getJson("/api/patients/{$id}/audits")->assertOk();

        $this->assertSame(['restored', 'deleted', 'created'], array_column($response->json('data'), 'event'));
        // Contract: `{}` (object), not `[]`. restore() saves deleted_at, which must not add an "updated" entry.
        $this->assertStringContainsString('"event":"restored","changes":{}', (string) $response->getContent());
        $this->assertStringContainsString('"event":"deleted","changes":{}', (string) $response->getContent());
    }

    public function test_history_of_a_trashed_patient_is_available(): void
    {
        $id = $this->createViaApi();
        $this->deleteJson("/api/patients/{$id}")->assertNoContent();

        $this->getJson("/api/patients/{$id}")->assertNotFound();
        $this->getJson("/api/patients/{$id}/audits")
            ->assertOk()
            ->assertJsonPath('data.0.event', 'deleted')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_missing_or_non_numeric_patient_returns_404(): void
    {
        $this->getJson('/api/patients/999/audits')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Paciente não encontrado.']);

        $this->getJson('/api/patients/abc/audits')->assertNotFound();
        $this->getJson('/api/patients/01/audits')->assertNotFound();
    }

    public function test_history_is_newest_first_with_id_desc_tie_break_and_paginated(): void
    {
        Carbon::setTestNow('2026-10-27 10:00:00');
        $patient = Patient::factory()->create();
        // Same second as "created": the id breaks the tie.
        $patient->update(['name' => 'Primeira Alteração']);
        Carbon::setTestNow('2026-10-27 11:00:00');
        $patient->update(['name' => 'Segunda Alteração']);
        Carbon::setTestNow();

        $ids = PatientAudit::where('patient_id', $patient->id)->orderBy('id')->pluck('id')->all();

        $this->assertSame(array_reverse($ids), array_column($this->getJson("/api/patients/{$patient->id}/audits")->json('data'), 'id'));

        $this->getJson("/api/patients/{$patient->id}/audits?per_page=2&page=2")
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.id', $ids[0]);

        $this->getJson("/api/patients/{$patient->id}/audits?per_page=abc")->assertJsonPath('meta.per_page', 10);
        $this->assertStringContainsString('per_page=1', $this->getJson("/api/patients/{$patient->id}/audits?per_page=1")->json('links.next'));
        $this->getJson("/api/patients/{$patient->id}/audits")->assertJsonPath('data.0.created_at', '2026-10-27T11:00:00-03:00');
    }

    public function test_history_only_lists_the_requested_patient(): void
    {
        $first = Patient::factory()->create();
        Patient::factory()->create();

        $this->getJson("/api/patients/{$first->id}/audits")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_changes_are_encrypted_at_rest(): void
    {
        $id = $this->createViaApi();

        $raw = DB::table('patient_audits')->where('patient_id', $id)->value('changes');

        $this->assertStringNotContainsString('62518953060', $raw);
        $this->assertStringNotContainsString('Ana Souza', $raw);
        $this->assertSame('62518953060', json_decode(Crypt::decryptString($raw), true)['cpf']['new']);
    }

    public function test_console_changes_have_no_ip_or_user_agent(): void
    {
        $patient = Patient::factory()->create();

        $audit = $patient->audits()->firstOrFail();

        $this->assertSame('created', $audit->event);
        $this->assertNull($audit->ip);
        $this->assertNull($audit->user_agent);
        $this->assertNull($audit->user_id);
    }

    public function test_user_agent_is_truncated(): void
    {
        $id = $this->withHeaders(['User-Agent' => str_repeat('a', 400)])
            ->postJson('/api/patients', $this->payload())
            ->json('data.id');

        $this->assertSame(255, mb_strlen(PatientAudit::where('patient_id', $id)->value('user_agent')));
    }

    public function test_seeding_and_without_auditing_create_no_entries(): void
    {
        $this->seed(PatientSeeder::class);
        $this->assertSame(30, Patient::count());
        $this->assertSame(0, PatientAudit::count());
        $this->assertSame(30, Patient::whereNotNull('cpf_hash')->count());

        PatientObserver::withoutAuditing(fn () => Patient::firstOrFail()->update(['name' => 'Sem Auditoria']));
        $this->assertSame(0, PatientAudit::count());

        // The flag is restored afterwards.
        Patient::firstOrFail()->update(['name' => 'Com Auditoria']);
        $this->assertSame(1, PatientAudit::count());
    }

    public function test_audit_belongs_to_the_patient_even_when_trashed(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $audit = PatientAudit::where('event', 'deleted')->firstOrFail();

        $this->assertSame($patient->id, $audit->patient?->id);
    }

    public function test_force_delete_removes_the_history(): void
    {
        $patient = Patient::factory()->create();
        $patient->update(['name' => 'Outro Nome']);

        $patient->forceDelete();

        $this->assertSame(0, PatientAudit::count());
    }
}
