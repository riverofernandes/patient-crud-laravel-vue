<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = $this->faker->randomElement(['M', 'F']);
        $gender = $sex === 'M' ? 'male' : 'female';

        return [
            'name' => $this->faker->firstName($gender).' '.$this->faker->lastName().' '.$this->faker->lastName(),
            'cpf' => $this->faker->unique()->cpf(false),
            'rg' => $this->faker->numerify('#########'),
            'birth_date' => $this->faker->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'sex' => $sex,
            'phone' => $this->faker->numerify('119########'),
            'email' => $this->faker->optional(0.6)->safeEmail(),
            'description' => $this->faker->optional(0.4)->randomElement([
                'Paciente com miopia leve, retorno anual.',
                'Usa lentes de contato. Alergia a colírios com conservantes.',
                'Histórico familiar de glaucoma.',
                'Pós-operatório de catarata, acompanhamento semestral.',
                'Astigmatismo. Prefere atendimento no período da manhã.',
                'Diabético, realizar mapeamento de retina anualmente.',
            ]),
        ];
    }
}
