<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Models\PatientAudit;
use Database\Seeders\ConsultationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_demo_consultations_without_audits_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(30, Patient::count());
        $this->assertSame(10, Consultation::count());
        $this->assertSame(3, EyeExam::count());
        $this->assertSame(0, PatientAudit::count());

        $statuses = Consultation::all()->countBy(fn (Consultation $c) => $c->status->value)->all();
        $this->assertSame(3, $statuses['completed']);
        $this->assertSame(1, $statuses['cancelled']);
        $this->assertSame(1, $statuses['no_show']);

        // Every eye exam belongs to a completed consultation; the default agenda week shows the upcoming ones.
        $this->assertSame(0, EyeExam::whereHas('consultation', fn ($q) => $q->where('status', '!=', ConsultationStatus::Completed->value))->count());
        $this->getJson('/api/consultations')->assertOk()->assertJsonCount(6, 'data');

        // The seeded rows respect the overlap rule.
        foreach (Consultation::all() as $consultation) {
            if ($consultation->status->blocksSchedule()) {
                $this->assertFalse(Consultation::occupyingSlot(
                    $consultation->professional_name, $consultation->scheduled_at, $consultation->ends_at, $consultation->id
                )->exists());
            }
        }

        $this->seed(ConsultationSeeder::class);
        $this->assertSame(10, Consultation::count());
    }

    public function test_does_nothing_without_enough_patients(): void
    {
        $this->seed(ConsultationSeeder::class);

        $this->assertSame(0, Consultation::count());
    }
}
