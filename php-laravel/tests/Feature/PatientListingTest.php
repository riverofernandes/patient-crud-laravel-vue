<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientListingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, int> ids in response order
     */
    private function ids(string $query): array
    {
        return array_column($this->getJson('/api/patients?'.$query)->assertOk()->json('data'), 'id');
    }

    /**
     * Three patients whose order differs for each sortable field.
     *
     * @return array<string, Patient>
     */
    private function patients(): array
    {
        return [
            'a' => Patient::factory()->create(['name' => 'Bruno', 'birth_date' => '1990-01-01', 'created_at' => '2026-01-03 10:00:00']),
            'b' => Patient::factory()->create(['name' => 'Carla', 'birth_date' => '1970-01-01', 'created_at' => '2026-01-01 10:00:00']),
            'c' => Patient::factory()->create(['name' => 'Ana', 'birth_date' => '1980-01-01', 'created_at' => '2026-01-02 10:00:00']),
        ];
    }

    public function test_it_sorts_by_name_in_both_directions(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->patients();

        $this->assertSame([$c->id, $a->id, $b->id], $this->ids('sort=name'));
        $this->assertSame([$c->id, $a->id, $b->id], $this->ids('sort=name&direction=asc'));
        $this->assertSame([$b->id, $a->id, $c->id], $this->ids('sort=name&direction=desc'));
    }

    public function test_it_sorts_by_birth_date_in_both_directions(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->patients();

        $this->assertSame([$b->id, $c->id, $a->id], $this->ids('sort=birth_date&direction=asc'));
        $this->assertSame([$a->id, $c->id, $b->id], $this->ids('sort=birth_date&direction=desc'));
    }

    public function test_it_sorts_by_created_at_in_both_directions(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->patients();

        $this->assertSame([$b->id, $c->id, $a->id], $this->ids('sort=created_at&direction=asc'));
        $this->assertSame([$a->id, $c->id, $b->id], $this->ids('sort=created_at&direction=desc'));
    }

    public function test_invalid_sort_and_direction_fall_back_to_name_asc(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->patients();
        $byName = [$c->id, $a->id, $b->id];

        $this->assertSame($byName, $this->ids(''));
        $this->assertSame($byName, $this->ids('sort=cpf'));
        $this->assertSame($byName, $this->ids('sort=id;drop table patients'));
        $this->assertSame($byName, $this->ids('direction=sideways'));
        $this->assertSame($byName, $this->ids('sort=nope&direction=DESC'));
        $this->assertSame([$b->id, $a->id, $c->id], $this->ids('sort=nope&direction=desc'));
    }

    public function test_ties_are_broken_by_id_ascending_in_both_directions(): void
    {
        $first = Patient::factory()->create(['name' => 'Mesmo Nome', 'birth_date' => '1990-05-05']);
        $second = Patient::factory()->create(['name' => 'Mesmo Nome', 'birth_date' => '1990-05-05']);
        $third = Patient::factory()->create(['name' => 'Mesmo Nome', 'birth_date' => '1990-05-05']);
        $expected = [$first->id, $second->id, $third->id];

        foreach (['name', 'birth_date'] as $sort) {
            $this->assertSame($expected, $this->ids("sort={$sort}&direction=asc"));
            $this->assertSame($expected, $this->ids("sort={$sort}&direction=desc"));
        }
    }

    public function test_sort_params_are_kept_in_pagination_links(): void
    {
        Patient::factory()->count(3)->create();

        $next = $this->getJson('/api/patients?per_page=1&sort=birth_date&direction=desc')->json('links.next');

        $this->assertStringContainsString('sort=birth_date', $next);
        $this->assertStringContainsString('direction=desc', $next);
    }

    public function test_trashed_only_lists_only_deleted_patients(): void
    {
        $active = Patient::factory()->create(['name' => 'Paciente Ativo']);
        $deleted = Patient::factory()->create(['name' => 'Paciente Removido']);
        $deleted->delete();

        $this->getJson('/api/patients?trashed=only')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deleted->id)
            ->assertJsonPath('data.0.deleted_at', $deleted->fresh()->deleted_at->toIso8601String());

        $this->getJson('/api/patients')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.deleted_at', null);

        // Any other value is a normal listing.
        $this->getJson('/api/patients?trashed=with')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->id);
    }

    public function test_trashed_only_applies_search_sort_and_pagination(): void
    {
        Patient::factory()->create(['name' => 'Eduardo Ativo']);
        $zeca = Patient::factory()->create(['name' => 'Eduardo Zeca', 'cpf' => '62518953060']);
        $ana = Patient::factory()->create(['name' => 'Eduardo Ana']);
        $other = Patient::factory()->create(['name' => 'Fernanda Lopes']);
        foreach ([$zeca, $ana, $other] as $patient) {
            $patient->delete();
        }

        $this->assertSame([$ana->id, $zeca->id], $this->ids('trashed=only&search=Eduardo'));
        $this->assertSame([$zeca->id, $ana->id], $this->ids('trashed=only&search=Eduardo&sort=name&direction=desc'));
        $this->assertSame([$zeca->id], $this->ids('trashed=only&search='.urlencode('625.189.530-60')));

        $this->getJson('/api/patients?trashed=only&per_page=2&page=2')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $other->id);
    }
}
