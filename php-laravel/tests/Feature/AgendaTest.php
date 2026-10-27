<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<int, int>
     */
    private function agendaIds(string $query = ''): array
    {
        return array_column($this->getJson('/api/consultations'.$query)->assertOk()->json('data'), 'id');
    }

    public function test_defaults_to_today_plus_six_days_in_sao_paulo(): void
    {
        // 01:00 UTC on Nov 3 is still Nov 2 in São Paulo.
        Carbon::setTestNow(Carbon::parse('2026-11-03T01:00:00Z'));

        $before = Consultation::factory()->at('2026-11-01T23:59')->create();
        $first = Consultation::factory()->at('2026-11-02T00:00')->create();
        $last = Consultation::factory()->at('2026-11-08T23:30')->create();
        $after = Consultation::factory()->at('2026-11-09T00:00')->create();

        $this->assertSame([$first->id, $last->id], $this->agendaIds());
        $this->assertNotContains($before->id, $this->agendaIds());
        $this->assertNotContains($after->id, $this->agendaIds());
    }

    public function test_range_is_inclusive_on_sao_paulo_day_boundaries(): void
    {
        $lateNight = Consultation::factory()->at('2026-11-03T23:30:00-03:00')->create(); // 02:30 UTC on Nov 4
        $midnight = Consultation::factory()->at('2026-11-04T00:00:00-03:00')->create();
        $utcSameDay = Consultation::factory()->at('2026-11-03T02:00:00Z')->create(); // 23:00 on Nov 2 in São Paulo

        $this->assertSame([$lateNight->id], $this->agendaIds('?date_from=2026-11-03&date_to=2026-11-03'));
        $this->assertSame([$midnight->id], $this->agendaIds('?date_from=2026-11-04&date_to=2026-11-04'));
        $this->assertSame([$utcSameDay->id], $this->agendaIds('?date_from=2026-11-02&date_to=2026-11-02'));
    }

    public function test_one_sided_ranges_span_a_week(): void
    {
        $nov10 = Consultation::factory()->at('2026-11-10T10:00')->create();
        $nov16 = Consultation::factory()->at('2026-11-16T10:00')->create();
        $nov17 = Consultation::factory()->at('2026-11-17T10:00')->create();

        $this->assertSame([$nov10->id, $nov16->id], $this->agendaIds('?date_from=2026-11-10'));
        $this->assertSame([$nov16->id, $nov17->id], $this->agendaIds('?date_to=2026-11-17'));
    }

    public function test_ordered_by_start_with_patient_name_and_no_pagination(): void
    {
        $patient = Patient::factory()->create(['name' => 'Maria da Silva']);
        $late = Consultation::factory()->for($patient)->at('2026-11-03T16:00')->create();
        $early = Consultation::factory()->for($patient)->at('2026-11-03T08:00')->create();
        Consultation::factory()->count(30)->sequence(fn ($s) => [
            'scheduled_at' => Carbon::parse('2026-11-05T08:00', 'America/Sao_Paulo')->addMinutes(30 * $s->index),
            'professional_name' => 'Dr. Agenda Cheia',
        ])->create();

        $response = $this->getJson('/api/consultations?date_from=2026-11-03&date_to=2026-11-05')
            ->assertOk()
            ->assertJsonCount(32, 'data')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links')
            ->assertJsonPath('data.0.id', $early->id)
            ->assertJsonPath('data.1.id', $late->id)
            ->assertJsonPath('data.0.patient', ['id' => $patient->id, 'name' => 'Maria da Silva']);

        $this->assertArrayNotHasKey('cpf', $response->json('data.0.patient'));
    }

    public function test_max_range_is_31_days_inclusive(): void
    {
        $this->getJson('/api/consultations?date_from=2026-01-01&date_to=2026-01-31')->assertOk();

        $this->getJson('/api/consultations?date_from=2026-01-01&date_to=2026-02-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_to.0', 'O intervalo da agenda não pode passar de 31 dias.');
    }

    public function test_invalid_dates_and_reversed_range(): void
    {
        $this->getJson('/api/consultations?date_from=2026-11-10&date_to=2026-11-09')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_to.0', 'O campo data final deve ser igual ou posterior à data inicial.');

        $this->getJson('/api/consultations?date_from=03/11/2026')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_from.0', 'O campo data inicial deve ser uma data válida no formato AAAA-MM-DD.');

        $this->getJson('/api/consultations?date_to=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors(['date_to']);
    }

    public function test_status_filter_accepts_a_comma_separated_list(): void
    {
        $scheduled = Consultation::factory()->at('2026-11-03T08:00')->create();
        $confirmed = Consultation::factory()->status(ConsultationStatus::Confirmed)->at('2026-11-03T09:00')->create();
        $cancelled = Consultation::factory()->status(ConsultationStatus::Cancelled)->at('2026-11-03T10:00')->create();

        $range = '?date_from=2026-11-03&date_to=2026-11-03';

        $this->assertSame([$scheduled->id, $confirmed->id, $cancelled->id], $this->agendaIds($range));
        $this->assertSame([$cancelled->id], $this->agendaIds($range.'&status=cancelled'));
        $this->assertSame([$scheduled->id, $confirmed->id], $this->agendaIds($range.'&status=scheduled,%20confirmed,'));

        $this->getJson('/api/consultations'.$range.'&status=scheduled,done')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Status inválido: done. Use: scheduled, confirmed, completed, no_show, cancelled.');
    }

    public function test_professional_filter_is_partial_and_ignores_case_and_accents(): void
    {
        $helena = Consultation::factory()->at('2026-11-03T08:00')->create(['professional_name' => 'Dra. Hélena Martins']);
        Consultation::factory()->at('2026-11-03T09:00')->create(['professional_name' => 'Dr. Ricardo Alves']);
        $percent = Consultation::factory()->at('2026-11-03T10:00')->create(['professional_name' => 'Dr. 100% Visão']);

        $range = '?date_from=2026-11-03&date_to=2026-11-03';

        $this->assertSame([$helena->id], $this->agendaIds($range.'&professional=H%C3%89LENA'));
        $this->assertSame([$helena->id], $this->agendaIds($range.'&professional=martins'));
        $this->assertSame([$helena->id], $this->agendaIds($range.'&professional=helena'));
        $this->assertSame([$percent->id], $this->agendaIds($range.'&professional=VISAO'));
        // LIKE wildcards are literal.
        $this->assertSame([$percent->id], $this->agendaIds($range.'&professional=%25'));
        $this->assertCount(3, $this->agendaIds($range.'&professional='));
    }

    public function test_consultations_of_trashed_patients_are_hidden(): void
    {
        $visible = Consultation::factory()->at('2026-11-03T08:00')->create();
        $hidden = Consultation::factory()->at('2026-11-03T09:00')->create();
        $hidden->patient?->delete();

        $this->assertSame([$visible->id], $this->agendaIds('?date_from=2026-11-03&date_to=2026-11-03'));
    }
}
