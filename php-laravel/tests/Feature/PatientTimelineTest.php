<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Models\PatientAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<int, string> "type:id" of each entry
     */
    private function entries(Patient $patient, string $query = ''): array
    {
        $data = $this->getJson("/api/patients/{$patient->id}/timeline{$query}")->assertOk()->json('data');

        return array_map(fn (array $entry) => $entry['type'].':'.$entry[$entry['type']]['id'], $data);
    }

    public function test_merges_consultations_and_audits_newest_first(): void
    {
        Carbon::setTestNow('2026-10-27 10:00:00');
        $patient = Patient::factory()->create(); // audit "created" at 10:00
        Carbon::setTestNow('2026-10-28 09:00:00');
        $patient->update(['name' => 'Nome Alterado']); // audit "updated" at 2026-10-28 09:00
        Carbon::setTestNow();

        $past = Consultation::factory()->for($patient)->at('2026-10-20T08:00')->create();
        $between = Consultation::factory()->for($patient)->at('2026-10-27T15:00')->create();
        $future = Consultation::factory()->for($patient)->at('2026-11-10T09:00')->create();
        Consultation::factory()->at('2026-11-11T09:00')->create(); // another patient

        [$created, $updated] = PatientAudit::where('patient_id', $patient->id)->orderBy('id')->pluck('id')->all();

        $this->assertSame([
            "consultation:{$future->id}",
            "audit:{$updated}",
            "consultation:{$between->id}",
            "audit:{$created}",
            "consultation:{$past->id}",
        ], $this->entries($patient));
    }

    public function test_entry_shapes(): void
    {
        Carbon::setTestNow('2026-10-27 10:00:00');
        $patient = Patient::factory()->create();
        Carbon::setTestNow();
        $consultation = Consultation::factory()->for($patient)->completed()->at('2026-11-03T14:30')->create();
        EyeExam::factory()->for($consultation)->create();

        $this->getJson("/api/patients/{$patient->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'consultation')
            ->assertJsonPath('data.0.occurred_at', '2026-11-03T14:30:00-03:00')
            ->assertJsonPath('data.0.consultation.id', $consultation->id)
            ->assertJsonPath('data.0.consultation.eye_exam.od.axis', 180)
            ->assertJsonMissingPath('data.0.audit')
            ->assertJsonPath('data.1.type', 'audit')
            ->assertJsonPath('data.1.occurred_at', '2026-10-27T10:00:00-03:00')
            ->assertJsonPath('data.1.audit.event', 'created')
            ->assertJsonPath('data.1.audit.changes.name.old', null)
            ->assertJsonMissingPath('data.1.consultation')
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_ties_put_consultations_before_audits_then_id_desc(): void
    {
        Carbon::setTestNow('2026-10-27 10:00:00');
        $patient = Patient::factory()->create();
        $patient->update(['name' => 'Mesmo Segundo']);
        Carbon::setTestNow();

        $first = Consultation::factory()->for($patient)->at('2026-10-27T10:00')->create();
        $second = Consultation::factory()->for($patient)->at('2026-10-27T10:00')->create(['professional_name' => 'Outro Profissional']);

        [$created, $updated] = PatientAudit::where('patient_id', $patient->id)->orderBy('id')->pluck('id')->all();

        $this->assertSame([
            "consultation:{$second->id}",
            "consultation:{$first->id}",
            "audit:{$updated}",
            "audit:{$created}",
        ], $this->entries($patient));
    }

    public function test_pagination_is_done_on_the_merged_list(): void
    {
        Carbon::setTestNow('2026-10-27 10:00:00');
        $patient = Patient::factory()->create();
        Carbon::setTestNow('2026-10-29 10:00:00');
        $patient->update(['name' => 'Segundo Nome']);
        Carbon::setTestNow();

        foreach (['2026-10-26T10:00', '2026-10-28T10:00', '2026-10-30T10:00'] as $at) {
            Consultation::factory()->for($patient)->at($at)->create();
        }

        $all = $this->entries($patient);
        $this->assertCount(5, $all);

        // Page boundaries fall between the two types.
        $this->assertSame(array_slice($all, 0, 2), $this->entries($patient, '?per_page=2'));
        $this->assertSame(array_slice($all, 2, 2), $this->entries($patient, '?per_page=2&page=2'));
        $this->assertSame(array_slice($all, 4, 1), $this->entries($patient, '?per_page=2&page=3'));
        $this->assertSame([], $this->entries($patient, '?per_page=2&page=4'));

        $this->getJson("/api/patients/{$patient->id}/timeline?per_page=2&page=2")
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2);

        $this->assertStringContainsString('per_page=2', (string) $this->getJson("/api/patients/{$patient->id}/timeline?per_page=2")->json('links.next'));
        $this->getJson("/api/patients/{$patient->id}/timeline?per_page=abc")->assertJsonPath('meta.per_page', 10);
    }
}
