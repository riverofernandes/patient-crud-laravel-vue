<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientAgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ageOf(string $birthDate): int
    {
        $patient = Patient::factory()->create(['birth_date' => $birthDate]);

        return $this->getJson("/api/patients/{$patient->id}")->assertOk()->json('data.age');
    }

    public function test_age_counts_the_birthday_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00', 'America/Sao_Paulo'));

        $this->assertSame(30, $this->ageOf('1996-10-26'));
    }

    public function test_age_does_not_count_the_birthday_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 23:59:59', 'America/Sao_Paulo'));

        $this->assertSame(29, $this->ageOf('1996-10-27'));
    }

    public function test_age_uses_the_sao_paulo_date_not_utc(): void
    {
        // 01:00 UTC on Oct 26 is still Oct 25 (22:00) in São Paulo: the birthday has not arrived yet.
        Carbon::setTestNow(Carbon::parse('2026-10-26 01:00:00', 'UTC'));
        $this->assertSame(29, $this->ageOf('1996-10-26'));

        // 01:00 UTC on Oct 27 is Oct 26 (22:00) in São Paulo: birthday today.
        Carbon::setTestNow(Carbon::parse('2026-10-27 01:00:00', 'UTC'));
        $this->assertSame(30, $this->ageOf('1996-10-26'));
    }

    public function test_age_of_a_leap_day_birthday(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-28 12:00:00', 'America/Sao_Paulo'));
        $this->assertSame(26, $this->ageOf('2000-02-29'));

        Carbon::setTestNow(Carbon::parse('2027-03-01 12:00:00', 'America/Sao_Paulo'));
        $this->assertSame(27, $this->ageOf('2000-02-29'));
    }

    public function test_age_of_a_baby_born_today_is_zero_and_listing_includes_age(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00', 'America/Sao_Paulo'));

        Patient::factory()->create(['birth_date' => '2026-10-26']);

        $this->getJson('/api/patients')->assertJsonPath('data.0.age', 0);
    }
}
