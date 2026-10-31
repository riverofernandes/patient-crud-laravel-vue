<?php

namespace App\Http\Requests;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /consultations/{id}/status: the target must be a known status reachable from the current one
 * (see ConsultationStatus::canTransitionTo()).
 */
class ConsultationStatusRequest extends FormRequest
{
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
            'status' => ['bail', 'required', 'string', Rule::in(ConsultationStatus::values())],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $consultation = $this->route('consultation');

                if ($validator->errors()->isNotEmpty() || ! $consultation instanceof Consultation) {
                    return;
                }

                $target = $this->targetStatus();

                // No overlap check here on purpose: every allowed transition starts from a status that already
                // blocks the slot (scheduled/confirmed), and cancelled/no_show are final, so a status change never
                // makes a consultation start occupying a slot. Overlaps can only come from creating or moving a
                // consultation (checked in ConsultationRequest) or from restoring a patient (which cancels the
                // conflicting future consultations, see PatientController::restore).
                if (! $consultation->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', sprintf(
                        'Não é possível alterar o status de "%s" para "%s".',
                        $consultation->status->label(),
                        $target->label()
                    ));
                }
            },
        ];
    }

    public function targetStatus(): ConsultationStatus
    {
        return ConsultationStatus::from((string) $this->input('status'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'O campo status é obrigatório.',
            'status.string' => 'O campo status deve ser um texto.',
            'status.in' => 'O status deve ser um destes: '.implode(', ', ConsultationStatus::values()).'.',
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
            'status' => [
                'description' => 'Novo status: `confirmed`, `completed`, `no_show` ou `cancelled` (conforme a tabela de transições).',
                'example' => 'confirmed',
            ],
        ];
    }
}
