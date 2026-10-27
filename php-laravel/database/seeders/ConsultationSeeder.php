<?php

namespace Database\Seeders;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConsultationSeeder extends Seeder
{
    private const HELENA = ['professional_name' => 'Dra. Helena Martins', 'professional_crm' => 'CRM-SP 123456'];

    private const RICARDO = ['professional_name' => 'Dr. Ricardo Alves', 'professional_crm' => 'CRM-SP 654321'];

    /**
     * Demo consultations for the first seeded patients, relative to today (São Paulo): past completed ones with
     * eye exams (one without refraction, so its prescription is a 422), a no-show, upcoming scheduled/confirmed
     * ones within the agenda's default week, and a cancelled one whose slot is taken by another patient.
     *
     * Idempotent: only seeds an empty table (container restarts don't duplicate data). Consultations have no
     * observer, so no patient audit entries are created. Rows respect the overlap rule and the eye-exam rules.
     */
    public function run(): void
    {
        if (Consultation::query()->exists()) {
            return;
        }

        $patients = Patient::query()->orderBy('id')->limit(8)->get()->all();

        if (count($patients) < 8) {
            return;
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $rows = [
            // [patient index, day offset, time, duration, professional, status, reason, eye exam]
            [0, -90, '09:00', 30, self::HELENA, ConsultationStatus::Completed, 'Dificuldade para enxergar de longe.', 'myopia'],
            [1, -45, '10:00', 40, self::RICARDO, ConsultationStatus::Completed, 'Dificuldade para ler de perto.', 'presbyopia'],
            [2, -14, '14:00', 30, self::HELENA, ConsultationStatus::Completed, 'Histórico familiar de glaucoma.', 'pressure'],
            [3, -7, '15:00', 30, self::RICARDO, ConsultationStatus::NoShow, 'Consulta de rotina.', null],
            [0, 1, '09:00', 30, self::HELENA, ConsultationStatus::Confirmed, 'Retorno para avaliação da adaptação aos óculos.', null],
            [4, 1, '09:30', 30, self::HELENA, ConsultationStatus::Scheduled, 'Ardência e olho seco.', null],
            [5, 2, '10:00', 45, self::RICARDO, ConsultationStatus::Scheduled, 'Dor de cabeça ao ler.', null],
            [6, 3, '11:00', 30, self::HELENA, ConsultationStatus::Cancelled, 'Consulta de rotina.', null],
            [7, 3, '11:00', 30, self::HELENA, ConsultationStatus::Scheduled, 'Visão embaçada à noite.', null],
            [1, 5, '16:00', 30, self::RICARDO, ConsultationStatus::Confirmed, 'Retorno com os óculos novos.', null],
        ];

        foreach ($rows as [$index, $days, $time, $duration, $professional, $status, $reason, $exam]) {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            /** @var Consultation $consultation */
            $consultation = $patients[$index]->consultations()->create($professional + [
                'scheduled_at' => $today->copy()->addDays($days)->setTime($hour, $minute),
                'duration_minutes' => $duration,
                'status' => $status,
                'reason' => $reason,
                'notes' => $status === ConsultationStatus::Completed ? 'Orientações entregues ao paciente.' : null,
            ]);

            if ($exam !== null) {
                $consultation->eyeExam()->create(self::exam($exam));
            }
        }
    }

    /**
     * @return array<string, string|float|int|null>
     */
    private static function exam(string $kind): array
    {
        return match ($kind) {
            'myopia' => [
                'od_visual_acuity_sc' => '20/40', 'od_visual_acuity_cc' => '20/20', 'od_spherical' => -1.25,
                'od_cylindrical' => -0.5, 'od_axis' => 180, 'od_iop' => 14,
                'oe_visual_acuity_sc' => '20/50', 'oe_visual_acuity_cc' => '20/20', 'oe_spherical' => -1.0,
                'oe_cylindrical' => 0.0, 'oe_iop' => 15,
                'diagnosis' => 'Miopia leve com astigmatismo no olho direito.',
            ],
            'presbyopia' => [
                'od_visual_acuity_sc' => '20/25', 'od_visual_acuity_cc' => '20/20', 'od_spherical' => 0.75,
                'od_cylindrical' => -0.75, 'od_axis' => 90, 'od_addition' => 2.0, 'od_iop' => 16,
                'oe_visual_acuity_sc' => '20/30', 'oe_visual_acuity_cc' => '20/20', 'oe_spherical' => 1.0,
                'oe_cylindrical' => -0.5, 'oe_axis' => 85, 'oe_addition' => 2.0, 'oe_iop' => 17,
                'diagnosis' => 'Presbiopia com hipermetropia leve.',
                'observations' => 'Lentes multifocais recomendadas.',
            ],
            // Acuity and pressure only: no refraction, so the prescription endpoint returns 422.
            default => [
                'od_visual_acuity_sc' => '20/20', 'od_iop' => 22,
                'oe_visual_acuity_sc' => '20/20', 'oe_iop' => 23,
                'diagnosis' => 'Pressão intraocular limítrofe; suspeita de glaucoma.',
                'observations' => 'Solicitar campimetria e retornar em 3 meses.',
            ],
        };
    }
}
