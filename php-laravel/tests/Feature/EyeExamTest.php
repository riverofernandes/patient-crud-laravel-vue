<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\EyeExam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EyeExamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'od' => ['visual_acuity_sc' => '20/40', 'visual_acuity_cc' => '20/20', 'spherical' => -1.25, 'cylindrical' => -0.5, 'axis' => 180, 'addition' => null, 'iop' => 14],
            'oe' => ['visual_acuity_sc' => '20/50', 'visual_acuity_cc' => '20/20', 'spherical' => -1.0, 'cylindrical' => 0, 'axis' => null, 'addition' => null, 'iop' => 15],
            'diagnosis' => 'Miopia leve com astigmatismo.',
            'observations' => null,
        ];
    }

    private function url(Consultation $consultation): string
    {
        return "/api/consultations/{$consultation->id}/eye-exam";
    }

    public function test_creates_the_exam_on_a_completed_consultation(): void
    {
        $consultation = Consultation::factory()->completed()->create();

        $this->putJson($this->url($consultation), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.id', $consultation->id)
            ->assertJsonPath('data.patient.id', $consultation->patient_id)
            ->assertJsonPath('data.eye_exam.od', $this->payload()['od'])
            ->assertJsonPath('data.eye_exam.oe.spherical', -1)
            ->assertJsonPath('data.eye_exam.oe.cylindrical', 0)
            ->assertJsonPath('data.eye_exam.diagnosis', 'Miopia leve com astigmatismo.')
            ->assertJsonPath('data.eye_exam.observations', null)
            ->assertJsonStructure(['data' => ['eye_exam' => ['updated_at']]]);

        // JSON numbers, not strings.
        $this->assertStringContainsString('"spherical":-1.25', (string) $this->getJson("/api/consultations/{$consultation->id}")->getContent());
    }

    public function test_put_replaces_the_whole_exam(): void
    {
        $consultation = Consultation::factory()->completed()->create();
        $this->putJson($this->url($consultation), $this->payload())->assertOk();

        $this->putJson($this->url($consultation), ['oe' => ['iop' => 18]])
            ->assertOk()
            ->assertJsonPath('data.eye_exam.od', array_fill_keys(EyeExam::MEASURES, null))
            ->assertJsonPath('data.eye_exam.oe.iop', 18)
            ->assertJsonPath('data.eye_exam.diagnosis', null);

        $this->assertSame(1, EyeExam::count());
    }

    public function test_empty_body_creates_an_empty_exam(): void
    {
        $consultation = Consultation::factory()->completed()->create();

        $this->putJson($this->url($consultation), [])->assertOk()->assertJsonPath('data.eye_exam.oe.spherical', null);
    }

    /**
     * @return array<string, array{ConsultationStatus}>
     */
    public static function notCompleted(): array
    {
        return [
            'scheduled' => [ConsultationStatus::Scheduled],
            'confirmed' => [ConsultationStatus::Confirmed],
            'no_show' => [ConsultationStatus::NoShow],
            'cancelled' => [ConsultationStatus::Cancelled],
        ];
    }

    #[DataProvider('notCompleted')]
    public function test_only_completed_consultations_accept_an_exam(ConsultationStatus $status): void
    {
        $consultation = Consultation::factory()->status($status)->create();

        $this->putJson($this->url($consultation), $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'O prontuário só pode ser registrado em atendimentos realizados.');

        $this->assertSame(0, EyeExam::count());
    }

    /**
     * Each case: per-eye overrides for "od", whether it is valid, and the expected message when it is not.
     *
     * @return array<string, array{array<string, mixed>, string|null, string|null}>
     */
    public static function eyeCases(): array
    {
        return [
            // Snellen
            'acuity 20/10' => [['visual_acuity_sc' => '20/10'], null, null],
            'acuity 20/400' => [['visual_acuity_cc' => '20/400'], null, null],
            'acuity 20/9' => [['visual_acuity_sc' => '20/9'], 'od.visual_acuity_sc', 'O campo acuidade visual sem correção (OD) deve estar no formato Snellen 20/N, com N entre 10 e 400.'],
            'acuity 20/500' => [['visual_acuity_sc' => '20/500'], 'od.visual_acuity_sc', null],
            'acuity leading zero' => [['visual_acuity_sc' => '20/040'], 'od.visual_acuity_sc', null],
            'acuity decimal' => [['visual_acuity_cc' => '0.5'], 'od.visual_acuity_cc', null],
            'acuity 6/6' => [['visual_acuity_cc' => '6/6'], 'od.visual_acuity_cc', null],
            // Spherical
            'spherical +30' => [['spherical' => 30], null, null],
            'spherical -30' => [['spherical' => -30], null, null],
            'spherical string' => [['spherical' => '-2.75'], null, null],
            'spherical 30.25' => [['spherical' => 30.25], 'od.spherical', 'O campo esférico (OD) deve estar entre -30 e 30.'],
            'spherical 0.3' => [['spherical' => 0.3], 'od.spherical', 'O campo esférico (OD) deve ser múltiplo de 0.25.'],
            'spherical text' => [['spherical' => 'abc'], 'od.spherical', 'O campo esférico (OD) deve ser um número.'],
            // Cylindrical / axis
            'cylinder positive' => [['cylindrical' => 0.5, 'axis' => 90], 'od.cylindrical', 'O campo cilíndrico (OD) deve estar entre -10 e 0.'],
            'cylinder -10' => [['cylindrical' => -10, 'axis' => 0], null, null],
            'cylinder -10.25' => [['cylindrical' => -10.25, 'axis' => 0], 'od.cylindrical', null],
            'cylinder 0.1 step' => [['cylindrical' => -0.1, 'axis' => 10], 'od.cylindrical', 'O campo cilíndrico (OD) deve ser múltiplo de 0.25.'],
            'cylinder without axis' => [['cylindrical' => -0.5, 'axis' => null], 'od.axis', 'O campo eixo (OD) é obrigatório quando o cilíndrico é diferente de zero.'],
            'cylinder axis missing key' => [['cylindrical' => '-0.75', 'axis' => '__unset'], 'od.axis', 'O campo eixo (OD) é obrigatório quando o cilíndrico é diferente de zero.'],
            'axis with zero cylinder' => [['cylindrical' => 0, 'axis' => 90], 'od.axis', 'O campo eixo (OD) deve ficar vazio quando o cilíndrico é zero ou não informado.'],
            'axis with "0" cylinder' => [['cylindrical' => '0', 'axis' => 90], 'od.axis', null],
            'axis with -0.00 cylinder' => [['cylindrical' => '-0.00', 'axis' => 90], 'od.axis', null],
            'axis without cylinder' => [['cylindrical' => null, 'axis' => 90], 'od.axis', null],
            'zero cylinder, null axis' => [['cylindrical' => 0, 'axis' => null], null, null],
            'axis 0' => [['axis' => 0], null, null],
            'axis 181' => [['axis' => 181], 'od.axis', 'O campo eixo (OD) deve estar entre 0 e 180.'],
            'axis decimal' => [['axis' => 90.5], 'od.axis', 'O campo eixo (OD) deve ser um número inteiro.'],
            // Addition
            'addition 0.75' => [['addition' => 0.75], null, null],
            'addition 4' => [['addition' => 4], null, null],
            'addition 0.5' => [['addition' => 0.5], 'od.addition', 'O campo adição (OD) deve estar entre 0.75 e 4.'],
            'addition 4.25' => [['addition' => 4.25], 'od.addition', null],
            'addition 1.1' => [['addition' => 1.1], 'od.addition', 'O campo adição (OD) deve ser múltiplo de 0.25.'],
            // IOP
            'iop 5' => [['iop' => 5], null, null],
            'iop 60' => [['iop' => 60], null, null],
            'iop 4' => [['iop' => 4], 'od.iop', 'O campo pressão intraocular (OD) deve estar entre 5 e 60.'],
            'iop 61' => [['iop' => 61], 'od.iop', null],
            'iop decimal' => [['iop' => 14.5], 'od.iop', 'O campo pressão intraocular (OD) deve ser um número inteiro.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('eyeCases')]
    public function test_eye_validation_matrix(array $overrides, ?string $errorField, ?string $message): void
    {
        $consultation = Consultation::factory()->completed()->create();
        $payload = $this->payload();
        $payload['od'] = array_merge($payload['od'], $overrides);
        $payload['od'] = array_filter($payload['od'], fn ($value) => $value !== '__unset');

        $response = $this->putJson($this->url($consultation), $payload);

        if ($errorField === null) {
            $response->assertOk();

            return;
        }

        $response->assertUnprocessable()->assertJsonValidationErrors([$errorField]);
        $this->assertSame([$errorField], array_keys($response->json('errors')));

        if ($message !== null) {
            // Keys such as "od.axis" contain a dot, so they are read from the decoded array.
            $this->assertSame($message, $response->json('errors')[$errorField][0]);
        }
    }

    public function test_left_eye_messages_and_text_limits(): void
    {
        $consultation = Consultation::factory()->completed()->create();

        $response = $this->putJson($this->url($consultation), [
            'oe' => ['spherical' => 1, 'cylindrical' => 0, 'axis' => 45],
            'diagnosis' => str_repeat('a', 2001),
            'observations' => str_repeat('a', 5001),
        ]);

        $this->assertSame('O campo eixo (OE) deve ficar vazio quando o cilíndrico é zero ou não informado.', $response->json('errors')['oe.axis'][0]);
        $response->assertUnprocessable()
            ->assertJsonPath('errors.diagnosis.0', 'O campo diagnóstico não pode ter mais de 2000 caracteres.')
            ->assertJsonPath('errors.observations.0', 'O campo observações não pode ter mais de 5000 caracteres.');

        $this->putJson($this->url($consultation), ['od' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.od.0', 'O campo olho direito (OD) deve ser um objeto.');

        $this->putJson($this->url($consultation), ['diagnosis' => str_repeat('a', 2000), 'observations' => str_repeat('a', 5000)])
            ->assertOk();
    }

    public function test_negative_zero_is_stored_as_zero(): void
    {
        $consultation = Consultation::factory()->completed()->create();

        $this->putJson($this->url($consultation), ['od' => ['spherical' => '-0.00', 'cylindrical' => '-0.0']])
            ->assertOk();

        $content = (string) $this->getJson("/api/consultations/{$consultation->id}")->getContent();
        $this->assertStringContainsString('"spherical":0,"cylindrical":0,', $content);
    }
}
