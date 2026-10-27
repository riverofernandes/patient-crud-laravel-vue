<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimelineEntryResource;
use App\Models\Patient;
use App\Support\PatientTimeline;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Atendimentos
 */
class PatientTimelineController extends Controller
{
    /**
     * Linha do tempo do paciente
     *
     * Atendimentos e registros do histórico de alterações do paciente numa só lista, do mais recente para o mais
     * antigo, paginada (`page`, `per_page` como na listagem). `occurred_at` é o `scheduled_at` do atendimento ou
     * o `created_at` do registro do histórico. Desempate: atendimentos antes de registros do histórico, depois
     * `id` decrescente. Paciente na lixeira → 404.
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @queryParam page integer Página desejada. Padrão: 1. Example: 1
     * @queryParam per_page integer Itens por página. Padrão: 10; máximo: 100. Example: 10
     *
     * @response 200 scenario="Sucesso" {"data": [{"type": "consultation", "occurred_at": "2026-11-03T14:30:00-03:00", "consultation": {"id": 1, "patient_id": 1, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "scheduled", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": null, "notes": null, "eye_exam": null, "created_at": "2026-10-27T10:05:00-03:00", "updated_at": "2026-10-27T10:05:00-03:00"}}, {"type": "audit", "occurred_at": "2026-10-27T10:00:00-03:00", "audit": {"id": 1, "event": "created", "changes": {"name": {"old": null, "new": "Maria da Silva"}}, "ip": "172.18.0.1", "user_agent": "Mozilla/5.0 (X11; Linux x86_64)", "user_id": null, "created_at": "2026-10-27T10:00:00-03:00"}}], "links": {"first": "http://localhost:8000/api/patients/1/timeline?page=1", "last": "http://localhost:8000/api/patients/1/timeline?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "from": 1, "last_page": 1, "links": [], "path": "http://localhost:8000/api/patients/1/timeline", "per_page": 10, "to": 2, "total": 2}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function __invoke(Request $request, Patient $patient): AnonymousResourceCollection
    {
        $entries = PatientTimeline::paginate($patient, PerPage::from($request))->withQueryString();

        return TimelineEntryResource::collection($entries);
    }
}
