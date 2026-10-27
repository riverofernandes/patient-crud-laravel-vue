<?php

namespace App\Http\Requests;

use App\Enums\ConsultationStatus;
use App\Http\Requests\Concerns\ClinicalValidationMessages;
use App\Models\Consultation;
use App\Models\EyeExam;
use App\Rules\Snellen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /consultations/{id}/eye-exam: creates or fully replaces the ophthalmic record of a completed consultation.
 * Every per-eye measure is optional; omitted ones are cleared.
 */
class EyeExamRequest extends FormRequest
{
    use ClinicalValidationMessages;

    public const NOT_COMPLETED_MESSAGE = 'O prontuário só pode ser registrado em atendimentos realizados.';

    private const EYE_LABELS = ['od' => 'OD', 'oe' => 'OE'];

    private const MEASURE_LABELS = [
        'visual_acuity_sc' => 'acuidade visual sem correção',
        'visual_acuity_cc' => 'acuidade visual com correção',
        'spherical' => 'esférico',
        'cylindrical' => 'cilíndrico',
        'axis' => 'eixo',
        'addition' => 'adição',
        'iop' => 'pressão intraocular',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];

        foreach (EyeExam::EYES as $eye) {
            $hasCylinder = fn (): bool => $this->cylinderOf($eye) !== 0.0;

            $rules += [
                $eye => ['nullable', 'array'],
                "{$eye}.visual_acuity_sc" => ['nullable', 'string', new Snellen()],
                "{$eye}.visual_acuity_cc" => ['nullable', 'string', new Snellen()],
                "{$eye}.spherical" => ['nullable', 'numeric', 'between:-30,30', 'multiple_of:0.25'],
                "{$eye}.cylindrical" => ['nullable', 'numeric', 'between:-10,0', 'multiple_of:0.25'],
                // Required when there is a cylinder, prohibited (absent or null) otherwise.
                "{$eye}.axis" => [
                    Rule::requiredIf($hasCylinder),
                    Rule::prohibitedIf(fn (): bool => ! $hasCylinder()),
                    'nullable',
                    'integer',
                    'between:0,180',
                ],
                "{$eye}.addition" => ['nullable', 'numeric', 'between:0.75,4', 'multiple_of:0.25'],
                "{$eye}.iop" => ['nullable', 'integer', 'between:5,60'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $consultation = $this->route('consultation');

                if ($consultation instanceof Consultation && $consultation->status !== ConsultationStatus::Completed) {
                    $validator->errors()->add('status', self::NOT_COMPLETED_MESSAGE);
                }
            },
        ];
    }

    /**
     * Validated input as eye_exams columns (od_spherical, ...), every column present (full replacement).
     *
     * @return array<string, string|float|int|null>
     */
    public function examData(): array
    {
        $validated = $this->validated();
        $data = [];

        foreach (EyeExam::EYES as $eye) {
            $values = is_array($validated[$eye] ?? null) ? $validated[$eye] : [];

            foreach (EyeExam::MEASURES as $measure) {
                $value = $values[$measure] ?? null;

                $data["{$eye}_{$measure}"] = match (true) {
                    $value === null => null,
                    in_array($measure, ['axis', 'iop'], true) => (int) $value,
                    in_array($measure, ['visual_acuity_sc', 'visual_acuity_cc'], true) => (string) $value,
                    // -0.0 would be returned as "-0.0"
                    default => (float) $value + 0.0,
                };
            }
        }

        $data['diagnosis'] = self::nullableString($validated['diagnosis'] ?? null);
        $data['observations'] = self::nullableString($validated['observations'] ?? null);

        return $data;
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        $messages = $this->clinicalMessages();

        foreach (array_keys(self::EYE_LABELS) as $eye) {
            $messages["{$eye}.axis.required"] = 'O campo :attribute é obrigatório quando o cilíndrico é diferente de zero.';
            $messages["{$eye}.axis.prohibited"] = 'O campo :attribute deve ficar vazio quando o cilíndrico é zero ou não informado.';
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'diagnosis' => 'diagnóstico',
            'observations' => 'observações',
            'od' => 'olho direito (OD)',
            'oe' => 'olho esquerdo (OE)',
        ];

        foreach (self::EYE_LABELS as $eye => $eyeLabel) {
            foreach (self::MEASURE_LABELS as $measure => $label) {
                $attributes["{$eye}.{$measure}"] = "{$label} ({$eyeLabel})";
            }
        }

        return $attributes;
    }

    /**
     * Body parameter documentation for Scribe (API docs at /docs).
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        $examples = [
            'od' => ['visual_acuity_sc' => '20/40', 'visual_acuity_cc' => '20/20', 'spherical' => -1.25, 'cylindrical' => -0.5, 'axis' => 180, 'addition' => null, 'iop' => 14],
            'oe' => ['visual_acuity_sc' => '20/50', 'visual_acuity_cc' => '20/20', 'spherical' => -1.0, 'cylindrical' => 0, 'axis' => null, 'addition' => null, 'iop' => 15],
        ];
        $descriptions = [
            'visual_acuity_sc' => 'Acuidade visual sem correção, Snellen `20/N` (N entre 10 e 400).',
            'visual_acuity_cc' => 'Acuidade visual com correção, Snellen `20/N` (N entre 10 e 400).',
            'spherical' => 'Esférico, de -30.00 a +30.00, múltiplo de 0.25.',
            'cylindrical' => 'Cilíndrico, de -10.00 a 0.00, múltiplo de 0.25.',
            'axis' => 'Eixo em graus (0 a 180). Obrigatório quando o cilíndrico é diferente de zero; deve ser `null` quando é zero ou ausente.',
            'addition' => 'Adição para perto, de +0.75 a +4.00, múltiplo de 0.25.',
            'iop' => 'Pressão intraocular em mmHg (inteiro, 5 a 60).',
        ];

        $parameters = [];

        foreach (self::EYE_LABELS as $eye => $eyeLabel) {
            $parameters[$eye] = ['description' => "Medidas do olho {$eyeLabel} (".($eye === 'od' ? 'direito' : 'esquerdo').'). Todas opcionais.'];

            foreach ($descriptions as $measure => $description) {
                $parameters["{$eye}.{$measure}"] = ['description' => $description, 'example' => $examples[$eye][$measure]];
            }
        }

        return $parameters + [
            'diagnosis' => ['description' => 'Diagnóstico (opcional, até 2000 caracteres). Armazenado criptografado.', 'example' => 'Miopia leve com astigmatismo.'],
            'observations' => ['description' => 'Observações (opcional, até 5000 caracteres). Armazenadas criptografadas.', 'example' => null],
        ];
    }

    /**
     * Cylinder of one eye as a number (0.0 when absent or not numeric: the numeric rule reports the latter).
     */
    private function cylinderOf(string $eye): float
    {
        $value = $this->input("{$eye}.cylindrical");

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
