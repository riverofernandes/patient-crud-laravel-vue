<?php

namespace App\Support;

use App\Models\Consultation;
use App\Models\EyeExam;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Glasses prescription (receita de óculos) of a consultation with refraction data, rendered by DomPDF from
 * resources/views/prescriptions/glasses.blade.php.
 */
final class Prescription
{
    public const NO_REFRACTION_MESSAGE = 'O atendimento não tem dados de refração para gerar a receita.';

    private readonly Patient $patient;

    private readonly EyeExam $exam;

    public function __construct(private readonly Consultation $consultation)
    {
        if ($consultation->patient === null || $consultation->eyeExam === null) {
            throw new LogicException('The prescription needs the consultation patient and eye exam.');
        }

        $this->patient = $consultation->patient;
        $this->exam = $consultation->eyeExam;
    }

    /**
     * `receita-<consultation id>-AAAAMMDD.pdf`, with the consultation date (São Paulo).
     */
    public function filename(): string
    {
        return 'receita-'.$this->consultation->id.'-'.$this->date()->format('Ymd').'.pdf';
    }

    public function html(): string
    {
        return view('prescriptions.glasses', $this->viewData())->render();
    }

    public function pdf(): string
    {
        return Pdf::loadHTML($this->html())->setPaper('a4')->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $rows = [];

        foreach (['od' => 'OD (olho direito)', 'oe' => 'OE (olho esquerdo)'] as $eye => $label) {
            $values = $this->exam->refraction($eye);

            $rows[] = [
                'label' => $label,
                'spherical' => Refraction::diopters($values['spherical']),
                'cylindrical' => Refraction::diopters($values['cylindrical']),
                'axis' => Refraction::axis($values['axis']),
                'addition' => Refraction::diopters($values['addition']),
            ];
        }

        return [
            'patientName' => $this->patient->name,
            'birthDate' => $this->patient->birth_date?->format('d/m/Y'),
            'age' => $this->patient->ageOn($this->date()),
            'date' => $this->date()->format('d/m/Y'),
            'rows' => $rows,
            'professionalName' => $this->consultation->professional_name,
            'professionalCrm' => $this->consultation->professional_crm,
        ];
    }

    private function date(): Carbon
    {
        return $this->consultation->scheduled_at->copy()->setTimezone(LocalDateTime::timezone());
    }
}
