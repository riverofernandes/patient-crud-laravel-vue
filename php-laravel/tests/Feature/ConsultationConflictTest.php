<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Overlap rule: same professional (ignoring case and accents), not cancelled/no_show, intervals [start, start + duration).
 */
class ConsultationConflictTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Este profissional já tem um atendimento neste horário.';

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = Patient::factory()->create();

        // Existing: 14:00–14:30 São Paulo.
        Consultation::factory()->at('2026-11-03T14:00', 30)->create(['professional_name' => 'Dr. Érico Souza']);
    }

    private function create(string $scheduledAt, int $duration = 30, string $professional = 'Dr. Érico Souza'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/patients/{$this->patient->id}/consultations", [
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $duration,
            'professional_name' => $professional,
        ]);
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function intervals(): array
    {
        return [
            'same slot' => ['2026-11-03T14:00', 30, true],
            'starts inside' => ['2026-11-03T14:15', 30, true],
            'ends inside' => ['2026-11-03T13:45', 30, true],
            'contains it' => ['2026-11-03T13:00', 120, true],
            'inside it' => ['2026-11-03T14:10', 10, true],
            'touching before' => ['2026-11-03T13:30', 30, false],
            'touching after' => ['2026-11-03T14:30', 30, false],
            'other day' => ['2026-11-04T14:00', 30, false],
            // 17:10 UTC = 14:10 São Paulo.
            'same instant in another offset' => ['2026-11-03T17:10:00Z', 10, true],
        ];
    }

    #[DataProvider('intervals')]
    public function test_overlap_matrix(string $start, int $duration, bool $conflicts): void
    {
        $response = $this->create($start, $duration);

        if ($conflicts) {
            $response->assertUnprocessable()
                ->assertJsonPath('errors.scheduled_at.0', self::MESSAGE)
                ->assertJsonPath('message', self::MESSAGE);
        } else {
            $response->assertCreated();
        }
    }

    public function test_professional_comparison_ignores_case_accents_and_extra_spaces(): void
    {
        $this->create('2026-11-03T14:00', 30, '  dr. ÉRICO   souza ')->assertUnprocessable();
        // Same as MySQL's utf8mb4_unicode_ci: accents are not significant.
        $this->create('2026-11-03T14:00', 30, 'Dr. Erico Souza')->assertUnprocessable();
        $this->create('2026-11-03T14:00', 30, 'Dra. Helena Martins')->assertCreated();
    }

    /**
     * @return array<string, array{ConsultationStatus, bool}>
     */
    public static function existingStatuses(): array
    {
        return [
            'scheduled blocks' => [ConsultationStatus::Scheduled, true],
            'confirmed blocks' => [ConsultationStatus::Confirmed, true],
            'completed blocks' => [ConsultationStatus::Completed, true],
            'no_show frees the slot' => [ConsultationStatus::NoShow, false],
            'cancelled frees the slot' => [ConsultationStatus::Cancelled, false],
        ];
    }

    #[DataProvider('existingStatuses')]
    public function test_only_active_consultations_block_the_slot(ConsultationStatus $status, bool $conflicts): void
    {
        Consultation::query()->update(['status' => $status->value]);

        $this->create('2026-11-03T14:00')->assertStatus($conflicts ? 422 : 201);
    }

    public function test_consultations_of_trashed_patients_do_not_block_the_slot(): void
    {
        Consultation::query()->firstOrFail()->patient?->delete();

        $this->create('2026-11-03T14:00')->assertCreated();
    }

    public function test_update_ignores_itself_but_not_others(): void
    {
        $existing = Consultation::query()->firstOrFail();
        $other = Consultation::factory()->at('2026-11-03T15:00', 30)->create(['professional_name' => 'Dr. Érico Souza']);

        // Extending itself to 14:00–14:45 does not conflict with itself.
        $this->putJson("/api/consultations/{$existing->id}", [
            'scheduled_at' => '2026-11-03T14:00',
            'duration_minutes' => 45,
            'professional_name' => 'Dr. Érico Souza',
        ])->assertOk();

        // Moving the other one into 14:30 overlaps 14:00–14:45.
        $this->putJson("/api/consultations/{$other->id}", [
            'scheduled_at' => '2026-11-03T14:30',
            'professional_name' => 'DR. ÉRICO SOUZA',
        ])->assertUnprocessable()->assertJsonPath('errors.scheduled_at.0', self::MESSAGE);
    }
}
