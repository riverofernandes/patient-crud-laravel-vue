<?php

namespace Database\Factories;

use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EyeExam>
 */
class EyeExamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory()->completed(),
            'od_visual_acuity_sc' => '20/40',
            'od_visual_acuity_cc' => '20/20',
            'od_spherical' => -1.25,
            'od_cylindrical' => -0.5,
            'od_axis' => 180,
            'od_addition' => null,
            'od_iop' => 14,
            'oe_visual_acuity_sc' => '20/50',
            'oe_visual_acuity_cc' => '20/20',
            'oe_spherical' => -1.0,
            'oe_cylindrical' => 0.0,
            'oe_axis' => null,
            'oe_addition' => null,
            'oe_iop' => 15,
            'diagnosis' => 'Miopia leve com astigmatismo.',
            'observations' => null,
        ];
    }

    /**
     * Only non-refraction data (acuity and pressure): no glasses prescription can be generated.
     */
    public function withoutRefraction(): static
    {
        return $this->state([
            'od_spherical' => null, 'od_cylindrical' => null, 'od_axis' => null, 'od_addition' => null,
            'oe_spherical' => null, 'oe_cylindrical' => null, 'oe_axis' => null, 'oe_addition' => null,
        ]);
    }
}
