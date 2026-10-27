<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<int, int> ids in response order (sorted by id for stable assertions)
     */
    private function ids(string $query): array
    {
        $ids = array_column($this->getJson('/api/patients?per_page=100&'.$query)->assertOk()->json('data'), 'id');
        sort($ids);

        return $ids;
    }

    public function test_it_filters_by_sex_and_ignores_other_values(): void
    {
        $m = Patient::factory()->create(['sex' => 'M']);
        $f = Patient::factory()->create(['sex' => 'F']);

        $this->assertSame([$m->id], $this->ids('sex=M'));
        $this->assertSame([$f->id], $this->ids('sex=F'));

        foreach (['sex=m', 'sex=X', 'sex=', 'sex[]=M'] as $query) {
            $this->assertSame([$m->id, $f->id], $this->ids($query), $query);
        }
    }

    public function test_age_bounds_are_inclusive_and_birthday_today_counts(): void
    {
        // 15:00 UTC is still the same day in São Paulo (12:00).
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:00:00', 'UTC'));

        $turns30Today = Patient::factory()->create(['birth_date' => '1996-10-26']);   // 30
        $turns30Tomorrow = Patient::factory()->create(['birth_date' => '1996-10-27']); // 29
        $turned31Today = Patient::factory()->create(['birth_date' => '1995-10-26']);   // 31
        $almost31 = Patient::factory()->create(['birth_date' => '1995-10-27']);        // 30
        $newborn = Patient::factory()->create(['birth_date' => '2026-10-26']);         // 0

        $this->assertSame([$turns30Today->id, $turned31Today->id, $almost31->id], $this->ids('age_min=30'));
        $this->assertSame([$turns30Today->id, $turns30Tomorrow->id, $almost31->id, $newborn->id], $this->ids('age_max=30'));
        $this->assertSame([$turns30Today->id, $almost31->id], $this->ids('age_min=30&age_max=30'));
        $this->assertSame([$newborn->id], $this->ids('age_max=0'));
        $this->assertSame([], $this->ids('age_min=31&age_max=30'));

        // The API's computed age agrees with the filter.
        foreach ($this->getJson('/api/patients?age_min=30&age_max=30')->json('data') as $patient) {
            $this->assertSame(30, $patient['age']);
        }
    }

    public function test_age_uses_the_sao_paulo_date(): void
    {
        // 01:00 UTC on Oct 27 is still Oct 26 (22:00) in São Paulo: the birthday on the 27th has not come yet.
        Carbon::setTestNow(Carbon::parse('2026-10-27 01:00:00', 'UTC'));

        $patient = Patient::factory()->create(['birth_date' => '1996-10-27']);

        $this->assertSame([], $this->ids('age_min=30'));
        $this->assertSame([$patient->id], $this->ids('age_max=29'));
    }

    public function test_leap_day_birthdays(): void
    {
        $leapling = Patient::factory()->create(['birth_date' => '2024-02-29']);

        // Non-leap year: still 0 on Feb 28, turns 1 on Mar 1 (same rule as the `age` field).
        Carbon::setTestNow('2025-02-28 12:00:00');
        $this->assertSame([], $this->ids('age_min=1'));
        $this->assertSame([$leapling->id], $this->ids('age_max=0'));

        Carbon::setTestNow('2025-03-01 12:00:00');
        $this->assertSame([$leapling->id], $this->ids('age_min=1'));
        $this->assertSame([], $this->ids('age_max=0'));

        // "Today" is Feb 29: someone born on Feb 28 of the previous year is already 1.
        Carbon::setTestNow('2028-02-29 12:00:00');
        $feb28 = Patient::factory()->create(['birth_date' => '2027-02-28']);
        $mar01 = Patient::factory()->create(['birth_date' => '2027-03-01']);
        $this->assertSame([$feb28->id], array_values(array_diff($this->ids('age_min=1&age_max=1'), [$leapling->id])));
        $this->assertSame([$mar01->id], $this->ids('age_max=0'));
    }

    public function test_invalid_age_values_are_ignored(): void
    {
        $young = Patient::factory()->create(['birth_date' => now()->subYears(10)->toDateString()]);
        $old = Patient::factory()->create(['birth_date' => now()->subYears(80)->toDateString()]);
        $all = [$young->id, $old->id];

        foreach (['age_min=-1', 'age_min=151', 'age_min=abc', 'age_min=1.5', 'age_min=', 'age_min[]=50', 'age_max=151', 'age_max=x', 'age_max=-5'] as $query) {
            $this->assertSame($all, $this->ids($query), $query);
        }

        $this->assertSame([$old->id], $this->ids('age_min=50&age_max=abc'));
        $this->assertSame([$young->id], $this->ids('age_min=abc&age_max=50'));
        $this->assertSame($all, $this->ids('age_min=0&age_max=150'));
    }

    public function test_filters_combine_with_search_sort_trash_and_pagination(): void
    {
        $birth = now()->subYears(40)->toDateString();
        $anaF = Patient::factory()->create(['name' => 'Ana Filtro', 'sex' => 'F', 'birth_date' => $birth]);
        $biaF = Patient::factory()->create(['name' => 'Bia Filtro', 'sex' => 'F', 'birth_date' => $birth]);
        Patient::factory()->create(['name' => 'Caio Filtro', 'sex' => 'M', 'birth_date' => $birth]);
        Patient::factory()->create(['name' => 'Dora Filtro', 'sex' => 'F', 'birth_date' => now()->subYears(10)->toDateString()]);
        $trashedF = Patient::factory()->create(['name' => 'Eva Filtro', 'sex' => 'F', 'birth_date' => $birth]);
        $trashedF->delete();

        $response = $this->getJson('/api/patients?search=Filtro&sex=F&age_min=30&age_max=50&sort=name&direction=desc&per_page=1')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $biaF->id);

        $next = $response->json('links.next');
        foreach (['search=Filtro', 'sex=F', 'age_min=30', 'age_max=50', 'sort=name', 'direction=desc', 'per_page=1'] as $param) {
            $this->assertStringContainsString($param, $next);
        }
        $this->getJson($next)->assertJsonPath('data.0.id', $anaF->id);

        $this->assertSame([$trashedF->id], $this->ids('trashed=only&sex=F&age_min=30'));
    }
}
