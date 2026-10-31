<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ClinicalValidationMessages;
use App\Models\Consultation;
use App\Rules\IsoDateTime;
use App\Support\LocalDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating (POST /patients/{id}/consultations) and fully replacing
 * (PUT /consultations/{id}) a consultation, including the professional's schedule conflict and the
 * final-status rule (only `notes` may change once completed, no_show or cancelled).
 */
class ConsultationRequest extends FormRequest
{
    use ClinicalValidationMessages;

    public const CONFLICT_MESSAGE = 'Este profissional já tem um atendimento neste horário.';

    public const FINAL_STATUS_MESSAGE = 'O campo :attribute não pode ser alterado em um atendimento finalizado (só as anotações podem mudar).';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['bail', 'required', 'string', new IsoDateTime()],
            'duration_minutes' => ['nullable', 'integer', 'between:'.Consultation::MIN_DURATION.','.Consultation::MAX_DURATION],
            'professional_name' => ['bail', 'required', 'string', 'min:3', 'max:255'],
            'professional_crm' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $consultation = $this->consultation();

                if ($consultation !== null && $consultation->status->isFinal()) {
                    $this->rejectChangesToFinalConsultation($validator, $consultation);

                    return;
                }

                $data = $this->consultationData();

                // Same slot as stored (time, duration and professional): nothing to re-check. Lets a consultation
                // that already overlaps another one (e.g. data from before the restore rule) still be edited.
                if ($consultation !== null && self::keepsSlot($data, $consultation)) {
                    return;
                }

                $start = $data['scheduled_at'];
                $end = $start->copy()->addMinutes($data['duration_minutes']);

                if (Consultation::occupyingSlot($data['professional_name'], $start, $end, $consultation?->id)->exists()) {
                    $validator->errors()->add('scheduled_at', self::CONFLICT_MESSAGE);
                }
            },
        ];
    }

    /**
     * Validated input normalized for the model: scheduled_at in the app timezone, defaults filled in
     * (PUT is a full replacement: omitted optional fields are cleared, the duration goes back to 30).
     *
     * @return array{scheduled_at: \Illuminate\Support\Carbon, duration_minutes: int, professional_name: string, professional_crm: string|null, reason: string|null, notes: string|null}
     */
    public function consultationData(): array
    {
        $input = $this->safe();

        return [
            'scheduled_at' => LocalDateTime::parse((string) $input['scheduled_at']),
            'duration_minutes' => isset($input['duration_minutes']) ? (int) $input['duration_minutes'] : Consultation::DEFAULT_DURATION,
            'professional_name' => (string) $input['professional_name'],
            'professional_crm' => self::nullableString($input['professional_crm'] ?? null),
            'reason' => self::nullableString($input['reason'] ?? null),
            'notes' => self::nullableString($input['notes'] ?? null),
        ];
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return $this->clinicalMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scheduled_at' => 'data e hora',
            'duration_minutes' => 'duração',
            'professional_name' => 'nome do profissional',
            'professional_crm' => 'CRM',
            'reason' => 'queixa principal',
            'notes' => 'anotações',
        ];
    }

    /**
     * Body parameter documentation for Scribe (API docs at /docs).
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'scheduled_at' => [
                'description' => 'Data e hora de início, ISO-8601 com fuso (`2026-11-03T14:30:00-03:00`) ou sem fuso (`2026-11-03T14:30`, interpretado em America/Sao_Paulo).',
                'example' => '2026-11-03T14:30:00-03:00',
            ],
            'duration_minutes' => [
                'description' => 'Duração em minutos (10 a 240). Padrão: 30.',
                'example' => 30,
            ],
            'professional_name' => [
                'description' => 'Nome do profissional (3 a 255 caracteres). Um mesmo nome (sem diferenciar maiúsculas nem acentos) não pode ter dois atendimentos ativos com horários sobrepostos.',
                'example' => 'Dra. Helena Martins',
            ],
            'professional_crm' => [
                'description' => 'CRM do profissional (opcional, até 20 caracteres).',
                'example' => 'CRM-SP 123456',
            ],
            'reason' => [
                'description' => 'Queixa principal (opcional, até 2000 caracteres). Armazenada criptografada.',
                'example' => 'Dificuldade para enxergar de longe.',
            ],
            'notes' => [
                'description' => 'Anotações (opcional, até 5000 caracteres). Armazenadas criptografadas. Único campo editável em atendimentos finalizados.',
                'example' => 'Paciente usa óculos há 5 anos.',
            ],
        ];
    }

    /**
     * The consultation being replaced (PUT), or null when creating. Also null when Scribe builds the request
     * without a route.
     */
    private function consultation(): ?Consultation
    {
        $consultation = $this->route('consultation');

        return $consultation instanceof Consultation ? $consultation : null;
    }

    /**
     * Final consultations keep everything but `notes`: any other field that differs is a 422 on that field.
     */
    private function rejectChangesToFinalConsultation(Validator $validator, Consultation $consultation): void
    {
        $data = $this->consultationData();
        $attributes = $this->attributes();

        $changed = [
            'scheduled_at' => ! $data['scheduled_at']->equalTo($consultation->scheduled_at),
            'duration_minutes' => $data['duration_minutes'] !== $consultation->duration_minutes,
            'professional_name' => $data['professional_name'] !== $consultation->professional_name,
            'professional_crm' => $data['professional_crm'] !== $consultation->professional_crm,
            'reason' => $data['reason'] !== $consultation->reason,
        ];

        foreach (array_keys(array_filter($changed)) as $field) {
            $validator->errors()->add($field, str_replace(':attribute', $attributes[$field], self::FINAL_STATUS_MESSAGE));
        }
    }

    /**
     * @param  array{scheduled_at: \Illuminate\Support\Carbon, duration_minutes: int, professional_name: string}  $data
     */
    private static function keepsSlot(array $data, Consultation $consultation): bool
    {
        return $data['scheduled_at']->equalTo($consultation->scheduled_at)
            && $data['duration_minutes'] === $consultation->duration_minutes
            && Consultation::professionalKey($data['professional_name']) === Consultation::professionalKey($consultation->professional_name);
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
