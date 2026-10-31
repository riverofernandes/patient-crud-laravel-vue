<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\EyeExam;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'scheduled_at' => '2026-11-03T14:30:00-03:00',
            'duration_minutes' => 30,
            'professional_name' => 'Dra. Helena Martins',
            'professional_crm' => 'CRM-SP 123456',
            'reason' => 'Dificuldade para enxergar de longe.',
            'notes' => null,
        ], $overrides);
    }

    public function test_create_returns_201_with_scheduled_status(): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.scheduled_at', '2026-11-03T14:30:00-03:00')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.professional_name', 'Dra. Helena Martins')
            ->assertJsonPath('data.reason', 'Dificuldade para enxergar de longe.')
            ->assertJsonPath('data.eye_exam', null)
            ->assertJsonMissingPath('data.patient')
            ->assertJsonMissingPath('data.ends_at')
            ->assertJsonMissingPath('data.professional_key');
    }

    public function test_create_ignores_a_status_in_the_body_and_defaults_the_duration(): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload([
            'status' => 'completed',
            'duration_minutes' => null,
            'professional_crm' => '',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.professional_crm', null);
    }

    public function test_date_times_are_normalized_to_sao_paulo(): void
    {
        $patient = Patient::factory()->create();

        // JavaScript Date#toISOString() output.
        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload(['scheduled_at' => '2026-11-03T17:30:00.000Z']))
            ->assertCreated()
            ->assertJsonPath('data.scheduled_at', '2026-11-03T14:30:00-03:00');

        // Without offset: São Paulo local time.
        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload(['scheduled_at' => '2026-11-04T09:00']))
            ->assertCreated()
            ->assertJsonPath('data.scheduled_at', '2026-11-04T09:00:00-03:00');

        $this->assertSame('2026-11-03 14:30:00', DB::table('consultations')->orderBy('id')->value('scheduled_at'));
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'scheduled_at missing' => [['scheduled_at' => null], 'scheduled_at', 'O campo data e hora é obrigatório.'],
            'scheduled_at relative' => [['scheduled_at' => 'tomorrow'], 'scheduled_at', 'O campo data e hora deve ser uma data e hora válida no formato ISO-8601 (AAAA-MM-DDTHH:MM, com ou sem fuso).'],
            'scheduled_at impossible' => [['scheduled_at' => '2026-02-30T10:00'], 'scheduled_at', 'O campo data e hora deve ser uma data e hora válida no formato ISO-8601 (AAAA-MM-DDTHH:MM, com ou sem fuso).'],
            'duration too short' => [['duration_minutes' => 9], 'duration_minutes', 'O campo duração deve estar entre 10 e 240.'],
            'duration too long' => [['duration_minutes' => 241], 'duration_minutes', 'O campo duração deve estar entre 10 e 240.'],
            'duration not integer' => [['duration_minutes' => 30.5], 'duration_minutes', 'O campo duração deve ser um número inteiro.'],
            'professional missing' => [['professional_name' => ''], 'professional_name', 'O campo nome do profissional é obrigatório.'],
            'professional too short' => [['professional_name' => 'Dr'], 'professional_name', 'O campo nome do profissional deve ter pelo menos 3 caracteres.'],
            'professional too long' => [['professional_name' => str_repeat('a', 256)], 'professional_name', 'O campo nome do profissional não pode ter mais de 255 caracteres.'],
            'crm too long' => [['professional_crm' => str_repeat('1', 21)], 'professional_crm', 'O campo CRM não pode ter mais de 20 caracteres.'],
            'reason too long' => [['reason' => str_repeat('a', 2001)], 'reason', 'O campo queixa principal não pode ter mais de 2000 caracteres.'],
            'notes too long' => [['notes' => str_repeat('a', 5001)], 'notes', 'O campo anotações não pode ter mais de 5000 caracteres.'],
            'notes not string' => [['notes' => ['x']], 'notes', 'O campo anotações deve ser um texto.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_validation_messages_are_in_portuguese(array $overrides, string $field, string $message): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}.0", $message);
    }

    public function test_limits_are_accepted(): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload([
            'duration_minutes' => 240,
            'reason' => str_repeat('a', 2000),
            'notes' => str_repeat('a', 5000),
            'professional_crm' => str_repeat('1', 20),
        ]))->assertCreated();
    }

    public function test_patient_listing_is_newest_first_paginated_and_scoped(): void
    {
        $patient = Patient::factory()->create();
        $old = Consultation::factory()->for($patient)->at('2026-10-01T10:00')->create();
        $new = Consultation::factory()->for($patient)->at('2026-12-01T10:00')->create();
        $sameTime = Consultation::factory()->for($patient)->at('2026-12-01T10:00')->create(['professional_name' => 'Outro Profissional']);
        Consultation::factory()->create(); // another patient
        EyeExam::factory()->for($old)->create();

        $response = $this->getJson("/api/patients/{$patient->id}/consultations")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'patient_id', 'scheduled_at', 'eye_exam']], 'links', 'meta']);

        // scheduled_at desc, id desc on ties.
        $this->assertSame([$sameTime->id, $new->id, $old->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.2.eye_exam.od.spherical', -1.25);

        $this->getJson("/api/patients/{$patient->id}/consultations?per_page=2&page=2")
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.id', $old->id);
    }

    public function test_nested_routes_return_404_for_missing_or_trashed_patients(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        foreach (["/api/patients/{$patient->id}/consultations", '/api/patients/999/consultations', "/api/patients/{$patient->id}/timeline"] as $url) {
            $this->getJson($url)->assertNotFound()->assertExactJson(['message' => 'Paciente não encontrado.']);
        }

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Paciente não encontrado.']);
    }

    public function test_show_includes_patient_id_and_name_only_and_eye_exam(): void
    {
        $exam = EyeExam::factory()->create();
        $consultation = $exam->consultation;
        $this->assertNotNull($consultation);
        $patient = $consultation->patient;
        $this->assertNotNull($patient);

        $this->getJson("/api/consultations/{$consultation->id}")
            ->assertOk()
            ->assertJsonPath('data.patient', ['id' => $patient->id, 'name' => $patient->name])
            ->assertJsonPath('data.eye_exam.od', [
                'visual_acuity_sc' => '20/40', 'visual_acuity_cc' => '20/20', 'spherical' => -1.25,
                'cylindrical' => -0.5, 'axis' => 180, 'addition' => null, 'iop' => 14,
            ])
            ->assertJsonPath('data.eye_exam.diagnosis', 'Miopia leve com astigmatismo.');
    }

    public function test_consultation_routes_return_404_for_missing_non_numeric_or_trashed_patient(): void
    {
        $consultation = Consultation::factory()->completed()->create();
        EyeExam::factory()->for($consultation)->create();
        $consultation->patient?->delete();

        $requests = [
            ['GET', "/api/consultations/{$consultation->id}"],
            ['PUT', "/api/consultations/{$consultation->id}"],
            ['PATCH', "/api/consultations/{$consultation->id}/status"],
            ['PUT', "/api/consultations/{$consultation->id}/eye-exam"],
            ['GET', "/api/consultations/{$consultation->id}/prescription"],
            ['GET', '/api/consultations/999'],
        ];

        foreach ($requests as [$method, $url]) {
            $this->json($method, $url, $this->payload())
                ->assertNotFound()
                ->assertExactJson(['message' => 'Atendimento não encontrado.']);
        }

        $this->getJson('/api/consultations/abc')->assertNotFound()->assertExactJson(['message' => 'Atendimento não encontrado.']);
        $this->getJson('/api/consultations/01')->assertNotFound();
    }

    public function test_update_replaces_editable_fields(): void
    {
        $consultation = Consultation::factory()->at('2026-11-03T14:30')->create(['notes' => 'Antes']);

        $this->putJson("/api/consultations/{$consultation->id}", [
            'scheduled_at' => '2026-11-03T15:00',
            'professional_name' => 'Dr. Ricardo Alves',
        ])
            ->assertOk()
            ->assertJsonPath('data.scheduled_at', '2026-11-03T15:00:00-03:00')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.professional_name', 'Dr. Ricardo Alves')
            // Full replacement: omitted optional fields are cleared.
            ->assertJsonPath('data.professional_crm', null)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.patient.id', $consultation->patient_id);

        $this->assertSame('2026-11-03 15:30:00', DB::table('consultations')->where('id', $consultation->id)->value('ends_at'));
    }

    /**
     * @return array<string, array{ConsultationStatus}>
     */
    public static function finalStatuses(): array
    {
        return [
            'completed' => [ConsultationStatus::Completed],
            'no_show' => [ConsultationStatus::NoShow],
            'cancelled' => [ConsultationStatus::Cancelled],
        ];
    }

    #[DataProvider('finalStatuses')]
    public function test_final_consultation_only_accepts_notes_changes(ConsultationStatus $status): void
    {
        $consultation = Consultation::factory()->status($status)->at('2026-11-03T14:30')->create([
            'professional_name' => 'Dra. Helena Martins',
            'professional_crm' => 'CRM-SP 123456',
            'reason' => 'Dificuldade para enxergar de longe.',
        ]);

        // Same values (scheduled_at in another offset format) + new notes: accepted.
        $this->putJson("/api/consultations/{$consultation->id}", $this->payload([
            'scheduled_at' => '2026-11-03T17:30:00.000Z',
            'notes' => 'Anotação posterior.',
        ]))
            ->assertOk()
            ->assertJsonPath('data.notes', 'Anotação posterior.')
            ->assertJsonPath('data.status', $status->value);

        // Any other field that differs: 422 on that field, nothing saved.
        $response = $this->putJson("/api/consultations/{$consultation->id}", $this->payload([
            'scheduled_at' => '2026-11-03T15:00',
            'duration_minutes' => 45,
            'professional_name' => 'dra. helena martins',
            'professional_crm' => null,
            'reason' => 'Outra queixa',
            'notes' => 'Não deve salvar.',
        ]))->assertUnprocessable();

        $this->assertSame(
            ['scheduled_at', 'duration_minutes', 'professional_name', 'professional_crm', 'reason'],
            array_keys($response->json('errors'))
        );
        $response->assertJsonPath('errors.reason.0', 'O campo queixa principal não pode ser alterado em um atendimento finalizado (só as anotações podem mudar).');
        $this->assertSame('Anotação posterior.', $consultation->fresh()?->notes);

        // Omitting duration_minutes means 30 (the current value): not a change.
        $this->putJson("/api/consultations/{$consultation->id}", array_diff_key($this->payload(), ['duration_minutes' => true]))->assertOk();
    }

    public function test_final_consultation_still_validates_the_payload(): void
    {
        $consultation = Consultation::factory()->completed()->create();

        $this->putJson("/api/consultations/{$consultation->id}", ['notes' => 'só notas'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at', 'professional_name']);
    }

    public function test_status_transitions_through_the_api(): void
    {
        $consultation = Consultation::factory()->create();

        $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.patient.id', $consultation->patient_id);

        $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => 'confirmed'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Não é possível alterar o status de "confirmado" para "confirmado".');

        $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => 'scheduled'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Não é possível alterar o status de "confirmado" para "agendado".');

        $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => 'completed'])->assertOk();

        foreach (ConsultationStatus::values() as $target) {
            $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => $target])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }

        $this->assertSame(ConsultationStatus::Completed, $consultation->fresh()?->status);
    }

    public function test_status_must_be_a_known_value(): void
    {
        $consultation = Consultation::factory()->create();

        $this->patchJson("/api/consultations/{$consultation->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'O campo status é obrigatório.');

        $this->patchJson("/api/consultations/{$consultation->id}/status", ['status' => 'done'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'O status deve ser um destes: scheduled, confirmed, completed, no_show, cancelled.');
    }

    public function test_clinical_text_is_encrypted_at_rest(): void
    {
        $patient = Patient::factory()->create();

        $id = $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload([
            'reason' => 'Queixa sigilosa',
            'notes' => 'Anotação sigilosa',
        ]))->json('data.id');

        $this->patchJson("/api/consultations/{$id}/status", ['status' => 'completed'])->assertOk();
        $this->putJson("/api/consultations/{$id}/eye-exam", [
            'diagnosis' => 'Diagnóstico sigiloso',
            'observations' => 'Observação sigilosa',
        ])->assertOk();

        $consultation = (array) DB::table('consultations')->where('id', $id)->first();
        $exam = (array) DB::table('eye_exams')->where('consultation_id', $id)->first();

        foreach (['reason' => 'Queixa sigilosa', 'notes' => 'Anotação sigilosa'] as $column => $plain) {
            $this->assertStringNotContainsString($plain, $consultation[$column]);
            $this->assertSame($plain, Crypt::decryptString($consultation[$column]));
        }

        foreach (['diagnosis' => 'Diagnóstico sigiloso', 'observations' => 'Observação sigilosa'] as $column => $plain) {
            $this->assertStringNotContainsString($plain, $exam[$column]);
            $this->assertSame($plain, Crypt::decryptString($exam[$column]));
        }

        $this->getJson("/api/consultations/{$id}")
            ->assertJsonPath('data.reason', 'Queixa sigilosa')
            ->assertJsonPath('data.eye_exam.diagnosis', 'Diagnóstico sigiloso');
    }

    public function test_consultations_do_not_create_patient_audits(): void
    {
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/consultations", $this->payload())->assertCreated();

        $this->assertSame(1, $patient->audits()->count());
    }

    public function test_force_deleting_a_patient_removes_consultations_and_exams(): void
    {
        $exam = EyeExam::factory()->create();
        $exam->consultation?->patient?->forceDelete();

        $this->assertSame(0, Consultation::count());
        $this->assertSame(0, EyeExam::count());
    }
}
