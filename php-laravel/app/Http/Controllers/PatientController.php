<?php

namespace App\Http\Controllers;

use App\Http\Requests\CpfAvailabilityRequest;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Rules\UniqueActiveCpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Pacientes
 *
 * Cadastro de pacientes: listagem com busca, ordenação e paginação, detalhe, criação, atualização,
 * exclusão lógica (lixeira), restauração e verificação de disponibilidade de CPF.
 */
class PatientController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    /**
     * Listar pacientes
     *
     * Retorna os pacientes ativos (não excluídos), com paginação no formato padrão do Laravel. Com `trashed=only`,
     * lista apenas os pacientes da lixeira (excluídos), com `deleted_at` preenchido; busca, ordenação e paginação
     * continuam valendo. Cada paciente traz `age` (idade em anos completos no fuso America/Sao_Paulo).
     *
     * @queryParam search string Busca por parte do nome ou pelo CPF (com ou sem máscara). Example: maria
     * @queryParam page integer Página desejada. Padrão: 1. Example: 1
     * @queryParam per_page integer Itens por página. Padrão: 10; máximo: 100 (valores maiores são limitados a 100). Example: 10
     * @queryParam sort string Campo de ordenação: `name`, `birth_date` ou `created_at`. Padrão: `name` (valor fora da lista também usa `name`). Example: name
     * @queryParam direction string Sentido da ordenação: `asc` ou `desc`. Padrão: `asc` (valor inválido também usa `asc`). O desempate é sempre por `id` crescente. Example: asc
     * @queryParam trashed string Use `only` para listar apenas os pacientes excluídos (lixeira). Example: only
     *
     * @responseFile 200 scenario="Sucesso" resources/docs/responses/patients.index.json
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: self::DEFAULT_PER_PAGE;
        $perPage = min($perPage, self::MAX_PER_PAGE);

        $patients = Patient::query()
            ->when($request->query('trashed') === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->search($request->query('search'))
            ->sorted($request->query('sort'), $request->query('direction'))
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
     * @response 201 scenario="Paciente criado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null}}
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
     * @urlParam id integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Sucesso" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null}}
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
     * @urlParam id integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Paciente atualizado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null}}
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
     * @urlParam id integer required ID do paciente. Example: 1
     *
     * @response 204 scenario="Paciente excluído"
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }

    /**
     * Restaurar paciente
     *
     * Restaura um paciente da lixeira (desfaz a exclusão lógica). Falha com 409 se outro paciente ativo
     * estiver usando o mesmo CPF (o CPF pode ter sido reutilizado depois da exclusão).
     *
     * @urlParam patient_id integer required ID do paciente excluído. Example: 1
     *
     * @response 200 scenario="Paciente restaurado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:45:00-03:00", "age": 30, "deleted_at": null}}
     * @response 404 scenario="Paciente não encontrado ou não excluído" {"message": "Paciente não encontrado."}
     * @response 409 scenario="CPF em uso por outro paciente ativo" {"message": "Já existe um paciente ativo com este CPF."}
     */
    public function restore(Patient $patient): PatientResource|JsonResponse
    {
        if (! $patient->trashed()) {
            // Same 404 body as a missing id: only patients in the trash can be restored.
            throw (new ModelNotFoundException())->setModel(Patient::class, [$patient->getKey()]);
        }

        if (Patient::cpfInUse($patient->cpf)) {
            return response()->json(['message' => 'Já existe um paciente ativo com este CPF.'], Response::HTTP_CONFLICT);
        }

        $patient->restore();

        return new PatientResource($patient);
    }

    /**
     * Verificar disponibilidade de CPF
     *
     * Informa se um CPF pode ser usado em um cadastro (não pertence a nenhum paciente ativo). Pacientes excluídos
     * não contam. Na edição, envie `ignore` com o ID do próprio paciente. CPF inválido retorna 422 com as mesmas
     * mensagens do cadastro.
     *
     * @response 200 scenario="CPF disponível" {"available": true}
     * @response 200 scenario="CPF em uso" {"available": false, "message": "Já existe um paciente cadastrado com este CPF."}
     * @response 422 scenario="CPF inválido" {"message": "O CPF informado é inválido.", "errors": {"cpf": ["O CPF informado é inválido."]}}
     */
    public function cpfAvailability(CpfAvailabilityRequest $request): JsonResponse
    {
        if (Patient::cpfInUse($request->validated('cpf'), $request->ignoredId())) {
            return response()->json(['available' => false, 'message' => UniqueActiveCpf::MESSAGE]);
        }

        return response()->json(['available' => true]);
    }
}
