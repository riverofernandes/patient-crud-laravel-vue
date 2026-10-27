<?php

namespace Database\Factories;

use App\Enums\ConsultationStatus;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            // Whole half hours in business time, so random rows rarely touch each other.
            'scheduled_at' => Carbon::now(config('app.timezone'))
                ->addDays($this->faker->numberBetween(1, 30))
                ->setTime($this->faker->numberBetween(8, 17), $this->faker->randomElement([0, 30])),
            'duration_minutes' => 30,
            'status' => ConsultationStatus::Scheduled,
            'professional_name' => 'Dr. '.$this->faker->firstName().' '.$this->faker->lastName(),
            'professional_crm' => 'CRM-SP '.$this->faker->numerify('######'),
            'reason' => $this->faker->optional(0.7)->randomElement([
                'Dificuldade para enxergar de longe.',
                'Dor de cabeça ao ler.',
                'Consulta de rotina.',
                'Ardência e olho seco.',
            ]),
            'notes' => null,
        ];
    }

    public function status(ConsultationStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function completed(): static
    {
        return $this->status(ConsultationStatus::Completed);
    }

    public function at(string $scheduledAt, int $duration = 30): static
    {
        return $this->state([
            'scheduled_at' => Carbon::parse($scheduledAt, config('app.timezone'))->setTimezone(config('app.timezone')),
            'duration_minutes' => $duration,
        ]);
    }
}
