<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Support\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function consultation(): Consultation
    {
        Carbon::setTestNow('2026-11-03 12:00:00');

        $patient = Patient::factory()->create(['name' => 'João Conceição', 'birth_date' => '1980-11-04']);
        $consultation = Consultation::factory()->for($patient)->completed()->at('2026-11-03T23:30')->create([
            'professional_name' => 'Dra. Helena Martins',
            'professional_crm' => 'CRM-SP 123456',
        ]);
        EyeExam::factory()->for($consultation)->create(['oe_addition' => 2.25, 'oe_spherical' => 1.25]);

        return $consultation;
    }

    public function test_downloads_a_pdf_with_the_contract_filename(): void
    {
        $consultation = $this->consultation();

        $response = $this->get("/api/consultations/{$consultation->id}/prescription")->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        // Consultation date in São Paulo (23:30 on Nov 3 is already Nov 4 in UTC).
        $response->assertHeader('Content-Disposition', 'attachment; filename="receita-'.$consultation->id.'-20261103.pdf"');

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);

        $text = (new Parser())->parseContent($pdf)->getText();

        $this->assertStringContainsString('Receita de óculos', $text);
        $this->assertStringContainsString('João Conceição', $text);
        $this->assertStringContainsString('03/11/2026', $text);
        $this->assertStringContainsString('Dra. Helena Martins', $text);
        $this->assertStringContainsString('CRM-SP 123456', $text);
    }

    public function test_view_formats_signs_axis_birth_date_and_age(): void
    {
        $consultation = $this->consultation();
        $consultation->load(['patient', 'eyeExam']);

        $html = (new Prescription($consultation))->html();

        // OD: -1.25 / -0.50 x 180; OE: +1.25, cylinder 0, addition +2.25.
        $this->assertStringContainsString("\u{2212}1.25", $html);
        $this->assertStringContainsString("\u{2212}0.50", $html);
        $this->assertStringContainsString("180\u{00B0}", $html);
        $this->assertStringContainsString('+1.25', $html);
        $this->assertStringContainsString('0.00', $html);
        $this->assertStringContainsString('+2.25', $html);
        // Age on the consultation date: the birthday (Nov 4) has not come yet.
        $this->assertStringContainsString('04/11/1980 (45 anos)', $html);
        $this->assertStringContainsString('DejaVu Sans', $html);
    }

    public function test_422_without_refraction_data(): void
    {
        $withoutExam = Consultation::factory()->completed()->create();
        $acuityOnly = EyeExam::factory()->withoutRefraction()->create();

        foreach ([$withoutExam->id, $acuityOnly->consultation_id] as $id) {
            $this->getJson("/api/consultations/{$id}/prescription")
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'O atendimento não tem dados de refração para gerar a receita.',
                    'errors' => ['eye_exam' => ['O atendimento não tem dados de refração para gerar a receita.']],
                ]);
        }
    }

    public function test_a_single_refraction_value_is_enough(): void
    {
        $exam = EyeExam::factory()->withoutRefraction()->create(['oe_addition' => 1.5]);

        $this->get("/api/consultations/{$exam->consultation_id}/prescription")->assertOk();
    }

    public function test_prescription_needs_the_patient_and_exam_loaded(): void
    {
        $this->expectException(\LogicException::class);

        new Prescription(Consultation::factory()->make()->setRelation('patient', null));
    }
}
