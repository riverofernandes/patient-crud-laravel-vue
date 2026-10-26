<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Pacientes
 *
 * Cadastro de pacientes: listagem com busca e paginação, detalhe, criação, atualização e exclusão lógica.
 */
class PatientController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    /**
     * Listar pacientes
     *
     * Retorna os pacientes ativos (não excluídos) ordenados por nome, com paginação no formato padrão do Laravel.
     *
     * @queryParam search string Busca por parte do nome ou pelo CPF (com ou sem máscara). Example: maria
     * @queryParam page integer Página desejada. Padrão: 1. Example: 1
     * @queryParam per_page integer Itens por página. Padrão: 10; máximo: 100 (valores maiores são limitados a 100). Example: 10
     *
     * @responseFile 200 scenario="Sucesso" resources/docs/responses/patients.index.json
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: self::DEFAULT_PER_PAGE;
        $perPage = min($perPage, self::MAX_PER_PAGE);

        $patients = Patient::query()
            ->search($request->query('search'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    /**
     * Cadastrar paciente
     *
     * Cria um paciente. CPF, RG e celular podem ser enviados com máscara; são armazenados só com dígitos.
     * O CPF deve ser válido e único entre os pacientes ativos.
     *
     * @response 201 scenario="Paciente criado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00"}}
     * @response 422 scenario="Erro de validação" {"message": "O campo nome completo é obrigatório. (and 1 more error)", "errors": {"name": ["O campo nome completo é obrigatório."], "cpf": ["O CPF informado é inválido."]}}
     */
    public function store(PatientRequest $request): PatientResource
    {
        $patient = Patient::create($request->validated());

        return new PatientResource($patient);
    }

    /**
     * Detalhar paciente
     *
     * @urlParam patient integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Sucesso" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient);
    }

    /**
     * Atualizar paciente
     *
     * Substituição completa (PUT): envie todos os campos. Campos opcionais omitidos (`email`, `description`) são apagados.
     *
     * @urlParam patient integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Paciente atualizado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     * @response 422 scenario="Erro de validação" {"message": "O campo nome completo é obrigatório. (and 1 more error)", "errors": {"name": ["O campo nome completo é obrigatório."], "cpf": ["O CPF informado é inválido."]}}
     */
    public function update(PatientRequest $request, Patient $patient): PatientResource
    {
        // PUT is a full replacement: optional fields left out are cleared.
        $patient->update(array_merge(['email' => null, 'description' => null], $request->validated()));

        return new PatientResource($patient);
    }

    /**
     * Excluir paciente
     *
     * Exclusão lógica (soft delete): o registro permanece no banco com `deleted_at` preenchido, deixa de aparecer
     * na listagem e passa a retornar 404. O CPF de um paciente excluído pode ser reutilizado em um novo cadastro.
     *
     * @urlParam patient integer required ID do paciente. Example: 1
     *
     * @response 204 scenario="Paciente excluído"
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }
}
