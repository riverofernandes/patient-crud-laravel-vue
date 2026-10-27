<?php

namespace App\Http\Controllers;

use App\Http\Requests\EyeExamRequest;
use App\Http\Resources\ConsultationResource;
use App\Models\Consultation;
use App\Support\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Atendimentos
 */
class EyeExamController extends Controller
{
    /**
     * Registrar prontuário oftalmológico
     *
     * Cria ou substitui (PUT) o prontuário do atendimento: acuidade visual, refração (esférico, cilíndrico, eixo,
     * adição) e pressão intraocular de cada olho (`od` = direito, `oe` = esquerdo), diagnóstico e observações.
     * Todos os campos são opcionais; os omitidos são apagados. Só para atendimentos `completed` (senão 422 em
     * `status`). Retorna o atendimento com o `eye_exam` gravado.
     *
     * @urlParam consultation_id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="Prontuário gravado" {"data": {"id": 1, "patient_id": 1, "patient": {"id": 1, "name": "Maria da Silva"}, "scheduled_at": "2026-11-03T14:30:00-03:00", "duration_minutes": 30, "status": "completed", "professional_name": "Dra. Helena Martins", "professional_crm": "CRM-SP 123456", "reason": "Dificuldade para enxergar de longe.", "notes": null, "eye_exam": {"od": {"visual_acuity_sc": "20/40", "visual_acuity_cc": "20/20", "spherical": -1.25, "cylindrical": -0.5, "axis": 180, "addition": null, "iop": 14}, "oe": {"visual_acuity_sc": "20/50", "visual_acuity_cc": "20/20", "spherical": -1.0, "cylindrical": 0.0, "axis": null, "addition": null, "iop": 15}, "diagnosis": "Miopia leve com astigmatismo.", "observations": null, "updated_at": "2026-11-03T15:00:00-03:00"}, "created_at": "2026-10-27T10:00:00-03:00", "updated_at": "2026-11-03T15:00:00-03:00"}}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     * @response 422 scenario="Atendimento não realizado" {"message": "O prontuário só pode ser registrado em atendimentos realizados.", "errors": {"status": ["O prontuário só pode ser registrado em atendimentos realizados."]}}
     * @response 422 scenario="Eixo sem cilíndrico" {"message": "O campo eixo (OE) deve ficar vazio quando o cilíndrico é zero ou não informado.", "errors": {"oe.axis": ["O campo eixo (OE) deve ficar vazio quando o cilíndrico é zero ou não informado."]}}
     */
    public function update(EyeExamRequest $request, Consultation $consultation): ConsultationResource
    {
        $consultation->eyeExam()->updateOrCreate([], $request->examData());

        return new ConsultationResource($consultation->load(['patient:id,name', 'eyeExam']));
    }

    /**
     * Receita de óculos (PDF)
     *
     * Baixa a receita de óculos do atendimento em PDF (`receita-<id>-AAAAMMDD.pdf`, com a data do atendimento):
     * paciente (nome, data de nascimento e idade), data, tabela OD/OE com esférico, cilíndrico, eixo e adição,
     * profissional e CRM, e linha para assinatura. `422` se o atendimento não tiver prontuário com ao menos um
     * valor de refração. O "Try it out" não exibe o PDF; para baixar, abra a URL no navegador.
     *
     * @urlParam consultation_id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="PDF (application/pdf)" %PDF-1.7 ...
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     * @response 422 scenario="Sem dados de refração" {"message": "O atendimento não tem dados de refração para gerar a receita.", "errors": {"eye_exam": ["O atendimento não tem dados de refração para gerar a receita."]}}
     */
    public function prescription(Consultation $consultation): Response|JsonResponse
    {
        $consultation->loadMissing(['patient', 'eyeExam']);

        if (! $consultation->eyeExam?->hasRefraction()) {
            return response()->json([
                'message' => Prescription::NO_REFRACTION_MESSAGE,
                'errors' => ['eye_exam' => [Prescription::NO_REFRACTION_MESSAGE]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $prescription = new Prescription($consultation);

        return response($prescription->pdf(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$prescription->filename().'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
