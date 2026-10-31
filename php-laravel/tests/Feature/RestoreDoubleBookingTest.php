<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Restoring a patient whose consultations lost their slot while they were in the trash.
 */
class RestoreDoubleBookingTest extends TestCase
{
    use RefreshDatabase;

    private const PROFESSIONAL = 'Dra. Helena Martins';

    private const NOTE = 'Cancelado automaticamente ao restaurar o paciente: o horário foi ocupado por outro atendimento.';

    private Patient $trashed;

    private Patient $other;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2027-05-01 09:00', 'America/Sao_Paulo'));

        $this->trashed = Patient::factory()->create();
        $this->other = Patient::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function consultation(Patient $patient, string $at, ConsultationStatus $status = ConsultationStatus::Scheduled, string $professional = self::PROFESSIONAL, string $notes = null): Consultation
    {
        return Consultation::factory()->for($patient)->at($at)->status($status)
            ->create(['professional_name' => $professional, 'notes' => $notes]);
    }

    public function test_restore_cancels_future_consultations_whose_slot_was_taken(): void
    {
        $mine = $this->consultation($this->trashed, '2027-05-10T10:00', notes: 'Trazer exames.');
        $this->trashed->delete();

        // Booked by someone else while the patient was in the trash (accent/case variant of the same professional).
        $this->postJson("/api/patients/{$this->other->id}/consultations", [
            'scheduled_at' => '2027-05-10T10:15',
            'professional_name' => 'DRA. HELENA MARTINS',
        ])->assertCreated();

        $this->postJson("/api/patients/{$this->trashed->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $this->trashed->id)
            ->assertJsonPath('meta', ['cancelled_consultations' => [[
                'id' => $mine->id,
                'scheduled_at' => '2027-05-10T10:00:00-03:00',
                'professional_name' => self::PROFESSIONAL,
            ]]]);

        $mine->refresh();
        $this->assertSame(ConsultationStatus::Cancelled, $mine->status);
        $this->assertSame("Trazer exames.\n\n".self::NOTE, $mine->notes);

        // The agenda no longer shows two active consultations in the same slot.
        $this->getJson('/api/consultations?date_from=2027-05-10&date_to=2027-05-10&status=scheduled,confirmed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_id', $this->other->id);
    }

    public function test_restore_without_conflicts_returns_an_empty_list_and_keeps_consultations(): void
    {
        $mine = $this->consultation($this->trashed, '2027-05-10T10:00', ConsultationStatus::Confirmed);
        // Other professional, and the same professional in a touching (non-overlapping) slot.
        $this->consultation($this->other, '2027-05-10T10:00', professional: 'Dr. Outro Nome');
        $this->consultation($this->other, '2027-05-10T10:30');
        $this->trashed->delete();

        $this->postJson("/api/patients/{$this->trashed->id}/restore")
            ->assertOk()
            ->assertJsonPath('meta.cancelled_consultations', []);

        $mine->refresh();
        $this->assertSame(ConsultationStatus::Confirmed, $mine->status);
        $this->assertNull($mine->notes);
    }

    public function test_past_final_and_non_blocking_consultations_are_left_alone(): void
    {
        $past = $this->consultation($this->trashed, '2027-04-20T10:00');
        $completed = $this->consultation($this->trashed, '2027-05-10T10:00', ConsultationStatus::Completed);
        $this->consultation($this->other, '2027-04-20T10:00');
        $this->consultation($this->other, '2027-05-10T10:00');
        // A cancelled booking of the other patient does not block anything.
        $free = $this->consultation($this->trashed, '2027-05-11T10:00');
        $this->consultation($this->other, '2027-05-11T10:00', ConsultationStatus::Cancelled);
        $this->trashed->delete();

        $this->postJson("/api/patients/{$this->trashed->id}/restore")
            ->assertOk()
            ->assertJsonPath('meta.cancelled_consultations', []);

        $this->assertSame(ConsultationStatus::Scheduled, $past->refresh()->status);
        $this->assertSame(ConsultationStatus::Completed, $completed->refresh()->status);
        $this->assertSame(ConsultationStatus::Scheduled, $free->refresh()->status);
    }

    public function test_only_the_conflicting_ones_are_cancelled_in_chronological_order(): void
    {
        $first = $this->consultation($this->trashed, '2027-05-10T10:00');
        $kept = $this->consultation($this->trashed, '2027-05-10T11:00');
        $second = $this->consultation($this->trashed, '2027-05-12T08:00', ConsultationStatus::Confirmed);
        $this->trashed->delete();
        $this->consultation($this->other, '2027-05-12T08:00', professional: 'DRA.  helena   Martins');
        $this->consultation($this->other, '2027-05-10T09:45');

        $this->postJson("/api/patients/{$this->trashed->id}/restore")
            ->assertOk()
            ->assertJsonPath('meta.cancelled_consultations.*.id', [$first->id, $second->id]);

        $this->assertSame(ConsultationStatus::Cancelled, $first->refresh()->status);
        $this->assertSame(self::NOTE, $first->notes);
        $this->assertSame(ConsultationStatus::Cancelled, $second->refresh()->status);
        $this->assertSame(ConsultationStatus::Scheduled, $kept->refresh()->status);
    }

    public function test_a_409_restore_changes_nothing(): void
    {
        $this->trashed->update(['cpf' => '62518953060']);
        $mine = $this->consultation($this->trashed, '2027-05-10T10:00');
        $this->trashed->delete();
        Patient::factory()->create(['cpf' => '62518953060']);
        $this->consultation($this->other, '2027-05-10T10:00');

        $this->postJson("/api/patients/{$this->trashed->id}/restore")->assertStatus(409);

        $this->assertSame(ConsultationStatus::Scheduled, $mine->refresh()->status);
    }

    public function test_a_consultation_that_already_overlaps_can_still_have_its_notes_edited(): void
    {
        // Overlapping rows written directly (e.g. data from before the restore rule).
        $existing = $this->consultation($this->other, '2027-05-10T10:00');
        $overlapping = $this->consultation($this->trashed, '2027-05-10T10:00');

        $this->putJson("/api/consultations/{$overlapping->id}", [
            'scheduled_at' => '2027-05-10T13:00:00Z', // same instant, another offset
            'professional_name' => ' DRA. HELENA  MARTINS ',
            'notes' => 'Só as anotações mudaram.',
        ])->assertOk()->assertJsonPath('data.notes', 'Só as anotações mudaram.');

        // Changing the slot (duration here) is still checked against the other consultation.
        $this->putJson("/api/consultations/{$overlapping->id}", [
            'scheduled_at' => '2027-05-10T10:00',
            'duration_minutes' => 45,
            'professional_name' => self::PROFESSIONAL,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.scheduled_at.0', 'Este profissional já tem um atendimento neste horário.');

        $this->assertSame(ConsultationStatus::Scheduled, $existing->refresh()->status);
    }
}
