<?php

namespace App\Http\Controllers;

use App\Http\Requests\CpfAvailabilityRequest;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\AuditResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Observers\PatientObserver;
use App\Rules\UniqueActiveCpf;
use App\Support\PatientCsv;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Pacientes
 *
 * Cadastro de pacientes: listagem com busca, filtros, ordenação e paginação, exportação em CSV, detalhe,
 * criação, atualização, exclusão lógica (lixeira), restauração, histórico de alterações e verificação de
 * disponibilidade de CPF.
 */
class PatientController extends Controller
{
    /** Rows fetched per query while streaming the CSV export (values are decrypted in PHP). */
    private const EXPORT_CHUNK = 500;

    /**
     * Listar pacientes
     *
     * Retorna os pacientes ativos (não excluídos), com paginação no formato padrão do Laravel. Com `trashed=only`,
     * lista apenas os pacientes da lixeira (excluídos), com `deleted_at` preenchido; busca, ordenação e paginação
     * continuam valendo. Cada paciente traz `age` (idade em anos completos no fuso America/Sao_Paulo).
     * Os filtros (`search`, `sex`, `age_min`, `age_max`) se combinam entre si e com a lixeira; todos os
     * parâmetros são mantidos nos links de paginação.
     *
     * @queryParam search string Busca por parte do nome, ou pelo CPF (com ou sem máscara): completo ou qualquer trecho de 3 ou mais dígitos. Example: maria
     * @queryParam sex string Filtra por sexo: `M` ou `F` (outro valor é ignorado). Example: F
     * @queryParam age_min integer Idade mínima em anos completos (0 a 150; valor inválido é ignorado). Example: 18
     * @queryParam age_max integer Idade máxima em anos completos (0 a 150; valor inválido é ignorado). Example: 65
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
        $patients = $this->filteredQuery($request)
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    /**
     * Exportar pacientes (CSV)
     *
     * Baixa os pacientes em CSV, com os mesmos filtros e ordenação da listagem (`search`, `sex`, `age_min`,
     * `age_max`, `sort`, `direction`, `trashed`) e sem paginação. Arquivo UTF-8 com BOM, separador `;` e
     * quebra de linha CRLF (abre direto no Excel em pt-BR), nome `pacientes-AAAAMMDD-HHMM.csv` (horário de
     * Brasília). Valores formatados: CPF `000.000.000-00`, celular `(00) 00000-0000`, datas `DD/MM/AAAA`,
     * sexo `Masculino`/`Feminino`, CEP `00000-000`, endereço "rua, número - complemento - bairro"; com `trashed=only` há a coluna extra `Excluído em` (`DD/MM/AAAA HH:MM`).
     * Células que começam com `=`, `+`, `-`, `@`, tab ou CR recebem um `'` na frente (proteção contra
     * injeção de fórmulas). O "Try it out" mostra o CSV como texto; para baixar o arquivo, abra a URL no
     * navegador.
     *
     * @queryParam search string Nome parcial ou CPF completo/parcial (como na listagem). Example: maria
     * @queryParam sex string `M` ou `F`. Example: F
     * @queryParam age_min integer Idade mínima (0 a 150). Example: 18
     * @queryParam age_max integer Idade máxima (0 a 150). Example: 65
     * @queryParam sort string `name`, `birth_date` ou `created_at`. Padrão: `name`. Example: name
     * @queryParam direction string `asc` ou `desc`. Padrão: `asc`. Example: asc
     * @queryParam trashed string `only` exporta apenas a lixeira (com a coluna `Excluído em`). Example: only
     *
     * @response 200 scenario="CSV (com BOM UTF-8)" Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descrição;CEP;Endereço;Cidade;UF
     * Maria da Silva;625.189.530-60;35688019X;20/07/1996;30;Feminino;(11) 92853-5718;maria@example.com;"Paciente com miopia leve; retorno anual.";01310-100;Avenida Paulista, 1578 - Sala 12 - Bela Vista;São Paulo;SP
     */
    public function export(Request $request): StreamedResponse
    {
        $withDeletedAt = $request->query('trashed') === 'only';
        $query = $this->filteredQuery($request);
        $filename = 'pacientes-'.Carbon::now(config('app.timezone'))->format('Ymd-Hi').'.csv';

        return response()->stream(function () use ($query, $withDeletedAt) {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, PatientCsv::BOM);
            PatientCsv::write($output, PatientCsv::header($withDeletedAt));

            // lazy() pages with the requested order (id tie-break keeps it deterministic); lazyById would
            // force id order.
            foreach ($query->lazy(self::EXPORT_CHUNK) as $patient) {
                PatientCsv::write($output, PatientCsv::row($patient, $withDeletedAt));
            }

            fclose($output);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Cadastrar paciente
     *
     * Cria um paciente. CPF, RG e celular podem ser enviados com máscara; são armazenados só com dígitos.
     * O CPF deve ser válido e único entre os pacientes ativos. `address` é opcional (objeto ou `null`); quando
     * enviado, todos os subcampos exceto `complement` são obrigatórios e os erros usam chaves com ponto
     * (`errors["address.cep"]`). A foto é enviada à parte (`POST /patients/{id}/photo`).
     *
     * @response 201 scenario="Paciente criado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null, "address": {"cep": "01310100", "street": "Avenida Paulista", "number": "1578", "complement": "Sala 12", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP"}, "photo_url": "http://localhost:8000/api/patients/1/photo?v=1"}}
     * @response 422 scenario="Erro de validação" {"message": "O campo nome completo é obrigatório. (and 1 more error)", "errors": {"name": ["O campo nome completo é obrigatório."], "cpf": ["O CPF informado é inválido."]}}
     */
    public function store(PatientRequest $request): PatientResource
    {
        $address = $request->addressData();

        $patient = DB::transaction(function () use ($request, $address) {
            $patient = new Patient($request->patientData());
            // Recorded in the same "created" audit as the patient fields.
            $patient->pendingAuditChanges = PatientAddress::auditChanges(null, $address);
            $patient->save();

            if ($address !== null) {
                $patient->address()->create($address);
            }

            return $patient;
        });

        return new PatientResource($patient->load('address'));
    }

    /**
     * Detalhar paciente
     *
     * @urlParam id integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Sucesso" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null, "address": {"cep": "01310100", "street": "Avenida Paulista", "number": "1578", "complement": "Sala 12", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP"}, "photo_url": "http://localhost:8000/api/patients/1/photo?v=1"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     */
    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient);
    }

    /**
     * Atualizar paciente
     *
     * `PUT` é substituição completa: envie todos os campos. Campos opcionais omitidos (`email`, `description`) são
     * apagados e `address` omitido ou `null` remove o endereço.
     *
     * `PATCH` é atualização parcial: só os campos enviados são validados e alterados (ex.: `{"name": "Novo nome"}`).
     * Campos omitidos ficam como estão; `address` omitido mantém o endereço e `null` o remove. Enviar um campo
     * obrigatório vazio continua sendo erro de validação.
     *
     * A foto não muda por aqui (use as rotas `/photo`).
     *
     * @urlParam id integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Paciente atualizado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:30:00-03:00", "age": 30, "deleted_at": null, "address": {"cep": "01310100", "street": "Avenida Paulista", "number": "1578", "complement": "Sala 12", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP"}, "photo_url": "http://localhost:8000/api/patients/1/photo?v=1"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     * @response 422 scenario="Erro de validação" {"message": "O campo nome completo é obrigatório. (and 1 more error)", "errors": {"name": ["O campo nome completo é obrigatório."], "cpf": ["O CPF informado é inválido."]}}
     */
    public function update(PatientRequest $request, Patient $patient): PatientResource
    {
        $address = $request->addressData();
        $touchesAddress = $request->touchesAddress();

        DB::transaction(function () use ($request, $patient, $address, $touchesAddress) {
            // PUT is a full replacement: optional fields left out are cleared (an omitted address is removed).
            // PATCH only changes what was sent.
            $defaults = $request->isPartial() ? [] : ['email' => null, 'description' => null];
            $patient->fill(array_merge($defaults, $request->patientData()));
            $patient->pendingAuditChanges = $touchesAddress
                ? PatientAddress::auditChanges($patient->address?->toResource(), $address)
                : [];

            if (! $patient->isDirty() && $patient->pendingAuditChanges !== []) {
                $patient->updateTimestamps(); // an address change also changes the patient record
            }

            $patient->save();
            // One audit per request: the address changes go with the patient's (or alone when only the address
            // changed and no "updated" event fired).
            PatientObserver::flushPending($patient);

            if (! $touchesAddress) {
                return;
            }

            if ($address === null) {
                $patient->address?->delete();
            } elseif ($patient->address !== null) {
                $patient->address->update($address);
            } else {
                $patient->address()->create($address);
            }
        });

        return new PatientResource($patient->unsetRelation('address')->load('address'));
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
     * @response 200 scenario="Paciente restaurado" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": "Paciente com miopia leve, retorno anual.", "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-10-26T15:45:00-03:00", "age": 30, "deleted_at": null, "address": {"cep": "01310100", "street": "Avenida Paulista", "number": "1578", "complement": "Sala 12", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP"}, "photo_url": "http://localhost:8000/api/patients/1/photo?v=1"}}
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

    /**
     * Histórico de alterações do paciente
     *
     * Lista as alterações do paciente, da mais recente para a mais antiga (desempate por `id` decrescente),
     * paginadas como a listagem. Funciona também para pacientes na lixeira. `event` é `created`, `updated`,
     * `deleted` ou `restored`; `changes` traz `{campo: {old, new}}` com os valores crus (como na API): todos os
     * campos em `created`, só os alterados em `updated` e `{}` em `deleted`/`restored`. O endereço aparece como
     * `address.<campo>` (ex.: `address.cep`) e a foto como `photo` (`{"old": null, "new": "adicionada" | "alterada" |
     * "removida"}`, nunca a imagem). `user_id` é sempre
     * `null` enquanto a API não tem autenticação. Os valores ficam criptografados no banco.
     *
     * @urlParam patient_id integer required ID do paciente (ativo ou na lixeira). Example: 1
     *
     * @queryParam page integer Página desejada. Padrão: 1. Example: 1
     * @queryParam per_page integer Itens por página. Padrão: 10; máximo: 100. Example: 10
     *
     * @response 200 scenario="Sucesso" {"data": [{"id": 2, "event": "updated", "changes": {"name": {"old": "Maria da Silva", "new": "Maria da Silva Lima"}, "phone": {"old": "11928535718", "new": "11911111111"}}, "ip": "172.18.0.1", "user_agent": "Mozilla/5.0 (X11; Linux x86_64)", "user_id": null, "created_at": "2026-10-27T10:00:00-03:00"}, {"id": 1, "event": "created", "changes": {"name": {"old": null, "new": "Maria da Silva"}, "cpf": {"old": null, "new": "62518953060"}, "rg": {"old": null, "new": "35688019X"}, "birth_date": {"old": null, "new": "1996-07-20"}, "sex": {"old": null, "new": "F"}, "phone": {"old": null, "new": "11928535718"}, "email": {"old": null, "new": "maria@example.com"}, "description": {"old": null, "new": null}}, "ip": "172.18.0.1", "user_agent": "Mozilla/5.0 (X11; Linux x86_64)", "user_id": null, "created_at": "2026-10-26T15:30:00-03:00"}], "links": {"first": "http://localhost:8000/api/patients/1/audits?page=1", "last": "http://localhost:8000/api/patients/1/audits?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "from": 1, "last_page": 1, "links": [{"url": null, "label": "&laquo; Previous", "active": false}, {"url": "http://localhost:8000/api/patients/1/audits?page=1", "label": "1", "active": true}, {"url": null, "label": "Next &raquo;", "active": false}], "path": "http://localhost:8000/api/patients/1/audits", "per_page": 10, "to": 2, "total": 2}}
     * @response 404 scenario="Paciente não encontrado" {"message": "Paciente não encontrado."}
     */
    public function audits(Request $request, Patient $patient): AnonymousResourceCollection
    {
        $audits = $patient->audits()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return AuditResource::collection($audits);
    }

    /**
     * Listing filters, trash and sorting shared by the listing and the CSV export.
     *
     * @return Builder<Patient>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = Patient::query()->with('address');

        if ($request->query('trashed') === 'only') {
            $query->onlyTrashed();
        }

        return $query
            ->search($request->query('search'))
            ->ofSex($request->query('sex'))
            ->ageBetween($request->query('age_min'), $request->query('age_max'))
            ->sorted($request->query('sort'), $request->query('direction'));
    }
}
