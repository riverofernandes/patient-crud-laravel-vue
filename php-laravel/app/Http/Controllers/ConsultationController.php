<?php

namespace App\Http\Controllers;

use App\Enums\ConsultationStatus;
use App\Http\Requests\AgendaRequest;
use App\Http\Requests\ConsultationRequest;
use App\Http\Requests\ConsultationStatusRequest;
use App\Http\Resources\ConsultationResource;
use App\Models\Consultation;
use App\Models\Patient;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Atendimentos
 *
 * Atendimentos (consultas) dos pacientes: agenda, criação, edição, mudança de status, prontuário oftalmológico,
 * receita de óculos em PDF e linha do tempo. Datas e horas em ISO-8601 com o deslocamento de São Paulo.
 * Os campos clínicos livres (`reason`, `notes`, `diagnosis`, `observations`) são armazenados criptografados.
 */
class ConsultationController extends Controller
{
    /**
     * Listar atendimentos do paciente
     *
     * Atendimentos do paciente, do mais recente para o mais antigo (`scheduled_at` decrescente, desempate por `id`
     * decrescente), paginados como a listagem de pacientes, cada um com `eye_exam` (ou `null`).
     * Paciente na lixeira → 404.
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @queryParam page integer Página desejada. Padrão: 1. Example: 1
     * @queryParam per_page integer Itens por página. Padrão: 10; máximo: 100. Example: 10
     *
     * @response 200 scenario="Sucesso" {"data": [{"id": 1, "patient_id": 1, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "completed", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Dificuldade para enxergar de longe.", "notes": null, "eye_exam": {"od": {"visual_acuity_sc": "20/40", "visual_acuity_cc": "20/20", "spherical": -1.25, "cylindrical": -0.5, "axis": 180, "addition": null, "iop": 14}, "oe": {"visual_acuity_sc": "20/50", "visual_acuity_cc": "20/20", "spherical": -1.0, "cylindrical": 0.0, "axis": null, "addition": null, "iop": 15}, "diagnosis": "Miopia leve com astigmatismo.", "observations": null, "updated_at": "2026-11-03T15:00:00-03:00"}, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-11-03T15:00:00-03:00"}], "links": {"first": "http://localhost:8000/api/patients/1/consultations?page=1", "last": "http://localhost:8000/api/patients/1/consultations?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "from": 1, "last_page": 1, "links": [], "path": "http://localhost:8000/api/patients/1/consultations", "per_page": 10, "to": 1, "total": 1}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function index(Request $request, Patient $patient): AnonymousResourceCollection
    {
        $consultations = $patient->consultations()
            ->with('eyeExam')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return ConsultationResource::collection($consultations);
    }

    /**
     * Agendar atendimento
     *
     * Cria um atendimento com `status = scheduled`. `422` em `scheduled_at` se o mesmo profissional (nome sem
     * diferenciar maiúsculas nem acentos) já tiver um atendimento não cancelado e não `no_show` com horário sobreposto
     * (intervalos `[início, início + duração)`; horários que só se encostam não conflitam).
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @response 201 scenario="Atendimento criado" {"data": {"id": 1, "patient_id": 1, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "scheduled", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Dificuldade para enxergar de longe.", "notes": null, "eye_exam": null, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-10-27T10:00:00-03:00"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     * @response 422 scenario="Conflito de horário" {"message": "Este profissional já tem um atendimento neste horário.", "errors": {"scheduled_at": ["Este profissional já tem um atendimento neste horário."]}}
     */
    public function store(ConsultationRequest $request, Patient $patient): JsonResponse
    {
        $consultation = $patient->consultations()->create(
            $request->consultationData() + ['status' => ConsultationStatus::Scheduled]
        );

        return (new ConsultationResource($consultation->setRelation('eyeExam', null)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Agenda
     *
     * Atendimentos que começam entre `date_from` e `date_to` (dias de São Paulo, inclusive), em ordem crescente de
     * `scheduled_at` (desempate por `id`), **sem paginação**, cada um com `patient` (`id` e `name`). Pacientes na
     * lixeira não aparecem. Intervalo máximo de 31 dias; `date_to` anterior a `date_from` → 422.
     *
     * @response 200 scenario="Sucesso" {"data": [{"id": 2, "patient_id": 1, "patient": {"id": 1, "name": "Maria da Silva"}, "scheduled_at": "2026-11-04T09:00:00-03:00", "duration_minutes": 30, "status": "confirmed", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Retorno.", "notes": null, "eye_exam": null, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-10-28T08:00:00-03:00"}]}
     * @response 422 scenario="Intervalo maior que 31 dias" {"message": "O intervalo da agenda não pode passar de 31 dias.", "errors": {"date_to": ["O intervalo da agenda não pode passar de 31 dias."]}}
     */
    public function agenda(AgendaRequest $request): AnonymousResourceCollection
    {
        [$from, $to] = $request->range();

        $consultations = Consultation::query()
            ->whereHas('patient')
            ->with(['patient:id,name', 'eyeExam'])
            ->startingBetween($from, $to->copy()->addDay())
            ->withStatuses($request->statuses())
            ->professionalLike($request->query('professional'))
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return ConsultationResource::collection($consultations);
    }

    /**
     * Detalhar atendimento
     *
     * Com `patient` (`id` e `name`) e `eye_exam`. `404` se não existir ou se o paciente estiver na lixeira.
     *
     * @urlParam id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="Sucesso" {"data": {"id": 1, "patient_id": 1, "patient": {"id": 1, "name": "Maria da Silva"}, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "scheduled", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Dificuldade para enxergar de longe.", "notes": null, "eye_exam": null, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-10-27T10:00:00-03:00"}}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     */
    public function show(Consultation $consultation): ConsultationResource
    {
        return new ConsultationResource($consultation->load(['patient:id,name', 'eyeExam']));
    }

    /**
     * Atualizar atendimento
     *
     * Substituição completa (PUT) dos campos editáveis (os mesmos da criação; opcionais omitidos são apagados e
     * a duração volta a 30). Em atendimento finalizado (`completed`, `no_show`, `cancelled`) só `notes` pode
     * mudar: qualquer outro campo diferente do atual → 422 naquele campo. O conflito de horário é verificado
     * ignorando o próprio atendimento.
     *
     * @urlParam id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="Atendimento atualizado" {"data": {"id": 1, "patient_id": 1, "patient": {"id": 1, "name": "Maria da Silva"}, "scheduled_at": "2026-11-03T15:00:00-03:00", "duration_minutes": 45, "status": "scheduled", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Dificuldade para enxergar de longe.", "notes": "Trazer óculos atuais.", "eye_exam": null, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-10-27T11:00:00-03:00"}}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     * @response 422 scenario="Atendimento finalizado" {"message": "O campo data e hora não pode ser alterado em um atendimento finalizado (só as anotações podem mudar).", "errors": {"scheduled_at": ["O campo data e hora não pode ser alterado em um atendimento finalizado (só as anotações podem mudar)."]}}
     */
    public function update(ConsultationRequest $request, Consultation $consultation): ConsultationResource
    {
        $data = $request->consultationData();

        $consultation->update($consultation->status->isFinal() ? ['notes' => $data['notes']] : $data);

        return new ConsultationResource($consultation->load(['patient:id,name', 'eyeExam']));
    }

    /**
     * Alterar status do atendimento
     *
     * Transições permitidas: `scheduled` → `confirmed`, `completed`, `no_show`, `cancelled`;
     * `confirmed` → `completed`, `no_show`, `cancelled`. `completed`, `no_show` e `cancelled` são finais.
     * Outras (inclusive para o mesmo status) → 422 em `status`.
     *
     * @urlParam consultation_id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="Status alterado" {"data": {"id": 1, "patient_id": 1, "patient": {"id": 1, "name": "Maria da Silva"}, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "confirmed", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": null, "notes": null, "eye_exam": null, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-10-28T08:00:00-03:00"}}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     * @response 422 scenario="Transição inválida" {"message": "Não é possível alterar o status de \"realizado\" para \"cancelado\".", "errors": {"status": ["Não é possível alterar o status de \"realizado\" para \"cancelado\"."]}}
     */
    public function updateStatus(ConsultationStatusRequest $request, Consultation $consultation): ConsultationResource
    {
        $consultation->update(['status' => $request->targetStatus()]);

        return new ConsultationResource($consultation->load(['patient:id,name', 'eyeExam']));
    }
}
