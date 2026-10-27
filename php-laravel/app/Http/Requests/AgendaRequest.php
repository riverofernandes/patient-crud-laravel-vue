<?php

namespace App\Http\Requests;

use App\Enums\ConsultationStatus;
use App\Http\Requests\Concerns\ClinicalValidationMessages;
use App\Support\LocalDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Query parameters of the agenda (GET /consultations).
 *
 * Range: São Paulo days, both ends inclusive, at most 31 days. Defaults: date_from = today, or date_to - 6 when
 * only date_to is sent; date_to = date_from + 6.
 */
class AgendaRequest extends FormRequest
{
    use ClinicalValidationMessages;

    public const MAX_DAYS = 31;

    public const DEFAULT_DAYS = 7;

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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string'],
            'professional' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invalid = array_diff($this->statuses(), ConsultationStatus::values());

                if ($invalid !== []) {
                    $validator->errors()->add('status', 'Status inválido: '.implode(', ', $invalid).'. Use: '.implode(', ', ConsultationStatus::values()).'.');
                }

                if ($validator->errors()->has('date_from') || $validator->errors()->has('date_to')) {
                    return;
                }

                [$from, $to] = $this->range();

                if ($to->lt($from)) {
                    $validator->errors()->add('date_to', 'O campo data final deve ser igual ou posterior à data inicial.');
                } elseif ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add('date_to', 'O intervalo da agenda não pode passar de '.self::MAX_DAYS.' dias.');
                }
            },
        ];
    }

    /**
     * First and last São Paulo day of the range (both at 00:00, inclusive).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $from = $this->dateParam('date_from');
        $to = $this->dateParam('date_to');
        $span = self::DEFAULT_DAYS - 1;

        $from ??= $to?->copy()->subDays($span) ?? LocalDateTime::today();
        $to ??= $from->copy()->addDays($span);

        return [$from, $to];
    }

    /**
     * Requested statuses (comma-separated list; blanks ignored, empty = all).
     *
     * @return array<int, string>
     */
    public function statuses(): array
    {
        $value = $this->query('status');

        if (! is_string($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), fn (string $s) => $s !== '')));
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
            'date_from' => 'data inicial',
            'date_to' => 'data final',
            'status' => 'status',
            'professional' => 'profissional',
        ];
    }

    /**
     * Query parameter documentation for Scribe (API docs at /docs).
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'date_from' => [
                'description' => 'Primeiro dia (AAAA-MM-DD, dia de São Paulo, inclusive). Padrão: hoje (ou `date_to` - 6 quando só `date_to` é enviado).',
                'example' => '2026-11-02',
            ],
            'date_to' => [
                'description' => 'Último dia (AAAA-MM-DD, inclusive). Padrão: `date_from` + 6. O intervalo pode ter no máximo 31 dias.',
                'example' => '2026-11-08',
            ],
            'status' => [
                'description' => 'Um ou vários status separados por vírgula: `scheduled`, `confirmed`, `completed`, `no_show`, `cancelled`. Valor desconhecido → 422.',
                'example' => 'scheduled,confirmed',
            ],
            'professional' => [
                'description' => 'Parte do nome do profissional (sem diferenciar maiúsculas nem acentos).',
                'example' => 'helena',
            ],
        ];
    }

    private function dateParam(string $key): ?Carbon
    {
        $value = $this->query($key);

        return is_string($value) && $value !== '' ? LocalDateTime::startOfDay($value) : null;
    }
}
