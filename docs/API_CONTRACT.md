# Contrato da API — Pacientes

Base URL (dev): `http://localhost:8000/api` (ou a porta definida em `API_PORT`)
Todas as respostas são JSON. Header recomendado: `Accept: application/json`.

## Recurso `Patient`

| Campo         | Tipo                 | Regras                                                                 |
|---------------|----------------------|------------------------------------------------------------------------|
| `id`          | int                  | somente leitura                                                        |
| `name`        | string               | obrigatório, 3–255                                                     |
| `cpf`         | string (11 dígitos)  | obrigatório, CPF válido (dígitos verificadores), único entre ativos. Aceita com ou sem máscara na entrada; **armazenado e retornado só com dígitos** |
| `rg`          | string               | obrigatório, até 20 chars. Aceita máscara; armazenado só com dígitos/letra X |
| `birth_date`  | string `YYYY-MM-DD`  | obrigatório, data válida, `before_or_equal:today`                      |
| `sex`         | enum `M` \| `F`      | obrigatório (Masculino / Feminino)                                     |
| `phone`       | string (10–11 dígitos) | obrigatório. Aceita máscara; armazenado só com dígitos              |
| `email`       | string \| null       | opcional, e-mail válido                                                |
| `description` | string \| null       | opcional, até 2000                                                     |
| `created_at`  | ISO-8601             | somente leitura                                                        |
| `updated_at`  | ISO-8601             | somente leitura                                                        |
| `age`         | int                  | somente leitura — idade em anos completos no fuso America/Sao_Paulo    |
| `deleted_at`  | ISO-8601 \| null     | somente leitura — preenchido só para pacientes na lixeira              |
| `address`     | `Address` \| null    | opcional (Sprint 5); ver "Endereço" |
| `photo_url`   | string \| null       | somente leitura (Sprint 5); URL absoluta de `GET /patients/{id}/photo` com `?v=<versão>` para invalidar cache, ou `null` |

Formatação visual (máscaras de CPF/RG/celular, data `DD/MM/AAAA`) é responsabilidade do front-end.

CPF, RG e celular são armazenados **criptografados** no banco (chave da aplicação). A unicidade e a busca por CPF usam um hash determinístico (blind index); a API sempre devolve os valores em claro.

Exclusão é **soft delete** (`deleted_at`). Pacientes excluídos não aparecem na listagem e retornam 404 no detalhe. Um CPF de paciente excluído pode ser reutilizado.

## Endpoints

### `GET /patients`
Query params:

| Param       | Default | Descrição |
|-------------|---------|-----------|
| `search`    | —       | Nome parcial, ou CPF **completo** (11 dígitos, com ou sem máscara). CPF parcial não é buscável, pois o CPF é armazenado criptografado |
| `page`      | 1       | Página |
| `per_page`  | 10      | Itens por página (1–100; inválido → 10) |
| `sort`      | `name`  | `name` \| `birth_date` \| `created_at` (valor fora da lista → `name`) |
| `direction` | `asc`   | `asc` \| `desc`, minúsculos (inválido → `asc`). Desempate sempre por `id` asc |
| `trashed`   | —       | `only` lista **apenas** pacientes excluídos (lixeira) |
| `sex`       | —       | `M` \| `F` (outro valor é ignorado) |
| `age_min`   | —       | Idade mínima em anos completos, inteiro 0–150 (inválido é ignorado) |
| `age_max`   | —       | Idade máxima em anos completos, inteiro 0–150 (inválido é ignorado) |

Na lixeira (`trashed=only`) cada item traz `deleted_at` preenchido; nas demais respostas `deleted_at` é `null`.

```json
200
{
  "data": [ { "id": 1, "name": "...", "cpf": "62518953060", "rg": "356880199", "birth_date": "1996-07-20", "sex": "M", "phone": "11928535718", "email": null, "description": null, "created_at": "...", "updated_at": "...", "age": 30, "deleted_at": null } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27, "from": 1, "to": 10 }
}
```
(formato padrão de `JsonResource::collection` paginado do Laravel)

### `GET /patients/{id}`
`200 { "data": Patient }` · `404 { "message": "Paciente não encontrado." }`

### `POST /patients`
Body: campos editáveis. `201 { "data": Patient }` · `422` (ver abaixo)

### `PUT /patients/{id}`
Body: todos os campos editáveis (substituição completa). `200 { "data": Patient }` · `404` · `422`

### `DELETE /patients/{id}`
Soft delete. `204` sem corpo · `404`

### `GET /patients/export`
Exporta pacientes em CSV. Aceita os mesmos filtros e ordenação da listagem (`search`, `sex`, `age_min`, `age_max`, `sort`, `direction`, `trashed`), sem paginação.

- `200`, `Content-Type: text/csv; charset=UTF-8`, `Content-Disposition: attachment; filename="pacientes-AAAAMMDD-HHMM.csv"` (horário de America/Sao_Paulo).
- UTF-8 com BOM, separador `;` (abre direto no Excel em pt-BR), quebra de linha `\r\n`.
- Cabeçalho: `Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descrição` (+ `;Excluído em` quando `trashed=only`).
- Valores formatados: CPF `000.000.000-00`, celular `(00) 00000-0000`, datas `DD/MM/AAAA`, sexo `Masculino`/`Feminino`.
- Células que começam com `=`, `+`, `-`, `@`, tab ou CR recebem um `'` na frente (proteção contra injeção de fórmulas).

### `GET /patients/{id}/audits`
Histórico de alterações do paciente, do mais recente para o mais antigo, paginado (`page`, `per_page` como na listagem). Funciona também para pacientes na lixeira. `404` se o id não existir.

```json
200
{
  "data": [
    {
      "id": 10,
      "event": "updated",
      "changes": { "name": { "old": "Ana Souza", "new": "Ana Souza Lima" }, "phone": { "old": "11900000000", "new": "11911111111" } },
      "ip": "172.18.0.1",
      "user_agent": "Mozilla/5.0 ...",
      "user_id": null,
      "created_at": "2026-10-27T10:00:00-03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

- `event`: `created` \| `updated` \| `deleted` \| `restored`.
- `changes`: em `created`, todos os campos com `old: null`; em `updated`, só os campos alterados; em `deleted`/`restored`, `{}`. Campos: `name`, `cpf`, `rg`, `birth_date`, `sex`, `phone`, `email`, `description` (valores crus, como na API).
- `user_id` é sempre `null` enquanto a API não tem autenticação.
- Os valores de `changes` também são armazenados criptografados.

### `POST /patients/{id}/restore`
Restaura um paciente da lixeira. `200 { "data": Patient }` ·
`404` se o id não existir ou o paciente não estiver excluído ·
`409 { "message": "Já existe um paciente ativo com este CPF." }` se outro paciente ativo usar o mesmo CPF.

### `GET /patients/cpf-availability?cpf={cpf}&ignore={id}`
Verifica se um CPF pode ser usado (aceita máscara; `ignore` opcional, id inteiro do paciente em edição — valor não inteiro → `422`).
`200 { "available": true }` ou `200 { "available": false, "message": "Já existe um paciente cadastrado com este CPF." }` ·
`422` se o CPF for inválido (mesmas mensagens da validação de cadastro).

### `GET /health`
Verifica a aplicação e a conexão com o banco. Não passa pelo rate limit.
`200 { "status": "ok" }` · `503 { "status": "unavailable", "message": "Banco de dados indisponível." }`

## Atendimentos (Sprint 4)

Datas e horas: a API recebe ISO-8601 com fuso (`2026-11-03T14:30:00-03:00`) ou sem fuso (`2026-11-03T14:30`, interpretado em America/Sao_Paulo) e devolve sempre ISO-8601 com o deslocamento de São Paulo. Campos clínicos livres (`reason`, `notes`, `observations`, `diagnosis`) são armazenados **criptografados**.

### Recurso `Consultation`

| Campo               | Tipo                | Regras |
|---------------------|---------------------|--------|
| `id`                | int                 | somente leitura |
| `patient_id`        | int                 | somente leitura (vem da rota) |
| `patient`           | `{ id, name }`      | somente leitura; presente na agenda (`GET /consultations`) |
| `scheduled_at`      | ISO-8601            | obrigatório na criação |
| `duration_minutes`  | int                 | 10–240, padrão 30 |
| `status`            | enum                | `scheduled` \| `confirmed` \| `completed` \| `no_show` \| `cancelled`; somente leitura (muda por `PATCH .../status`) |
| `professional_name` | string              | obrigatório, 3–255 |
| `professional_crm`  | string \| null      | opcional, até 20 |
| `reason`            | string \| null      | queixa principal, até 2000 |
| `notes`             | string \| null      | anotações, até 5000 |
| `eye_exam`          | `EyeExam` \| null   | somente leitura aqui (gravado por `PUT .../eye-exam`) |
| `created_at`, `updated_at` | ISO-8601     | somente leitura |

Conflito de horário: `422` em `scheduled_at` ("Este profissional já tem um atendimento neste horário.") se o mesmo `professional_name` (sem diferenciar maiúsculas) já tiver atendimento não cancelado e não `no_show` cujo intervalo `[scheduled_at, scheduled_at + duration)` se sobreponha.

Transições de `status` (outras → `422` com `errors.status`):

| De          | Para |
|-------------|------|
| `scheduled` | `confirmed`, `completed`, `no_show`, `cancelled` |
| `confirmed` | `completed`, `no_show`, `cancelled` |
| `completed`, `no_show`, `cancelled` | — (finais) |

Atendimentos de pacientes na lixeira não aparecem na agenda, e as rotas aninhadas no paciente retornam `404` para eles.

### Recurso `EyeExam` (prontuário oftalmológico)

```json
{
  "od": { "visual_acuity_sc": "20/40", "visual_acuity_cc": "20/20", "spherical": -1.25, "cylindrical": -0.5, "axis": 180, "addition": null, "iop": 14 },
  "oe": { "visual_acuity_sc": "20/50", "visual_acuity_cc": "20/20", "spherical": -1.0, "cylindrical": 0, "axis": null, "addition": null, "iop": 15 },
  "diagnosis": "Miopia leve com astigmatismo.",
  "observations": null,
  "updated_at": "2026-11-03T15:00:00-03:00"
}
```

Regras por olho (`od` = olho direito, `oe` = olho esquerdo), todos opcionais:

| Campo | Regra |
|-------|-------|
| `visual_acuity_sc` / `visual_acuity_cc` | acuidade sem/com correção, formato Snellen `20/N` (N entre 10 e 400) |
| `spherical` | −30.00 a +30.00, múltiplo de 0.25 |
| `cylindrical` | −10.00 a 0.00, múltiplo de 0.25 |
| `axis` | inteiro 0–180; obrigatório quando `cylindrical` ≠ 0, proibido (`null`) quando `cylindrical` é 0 ou ausente |
| `addition` | +0.75 a +4.00, múltiplo de 0.25 |
| `iop` | pressão intraocular em mmHg, inteiro 5–60 |

`diagnosis` até 2000, `observations` até 5000.

### Endpoints

#### `GET /patients/{id}/consultations`
Atendimentos do paciente, `scheduled_at` decrescente, paginado (`page`, `per_page`), com `eye_exam`.

#### `POST /patients/{id}/consultations`
Cria com `status = scheduled`. Body: `scheduled_at`, `duration_minutes?`, `professional_name`, `professional_crm?`, `reason?`, `notes?`. `201 { data }` · `404` · `422`.

#### `GET /consultations`
Agenda. Query: `date_from` e `date_to` (`YYYY-MM-DD`, dias de São Paulo; padrão hoje e hoje + 6; intervalo máximo de 31 dias, senão `422`), `status` (um ou vários separados por vírgula), `professional` (parte do nome). Ordem `scheduled_at` crescente, **sem paginação**, `{ data: Consultation[] }` com `patient`.

#### `GET /consultations/{id}` · `PUT /consultations/{id}`
Detalhe (com `patient` e `eye_exam`). `PUT` substitui os campos editáveis (mesmos do `POST`); em atendimento final (`completed`, `no_show`, `cancelled`) só `notes` pode mudar, os demais campos diferentes → `422`. `404` se não existir ou se o paciente estiver na lixeira.

#### `PATCH /consultations/{id}/status`
Body `{ "status": "confirmed" }`. `200 { data }` · `422` em transição inválida.

#### `PUT /consultations/{id}/eye-exam`
Cria ou substitui o prontuário. Só para `status = completed` (senão `422` com `errors.status`: "O prontuário só pode ser registrado em atendimentos realizados."). `200 { data: Consultation }`.

#### `GET /consultations/{id}/prescription`
Receita de óculos em PDF (`application/pdf`, `Content-Disposition: attachment; filename="receita-<id-do-atendimento>-AAAAMMDD.pdf"`), com paciente, data, tabela OD/OE (esférico, cilíndrico, eixo, adição), profissional e CRM. `422` se não houver prontuário com ao menos um valor de refração.

#### `GET /patients/{id}/timeline`
Linha do tempo do paciente, mais recente primeiro, paginada:

```json
{ "data": [
  { "type": "consultation", "occurred_at": "2026-11-03T14:30:00-03:00", "consultation": { ...Consultation } },
  { "type": "audit", "occurred_at": "2026-10-27T10:00:00-03:00", "audit": { ...Audit } }
], "links": { }, "meta": { } }
```

`occurred_at` = `scheduled_at` para atendimentos e `created_at` para registros do histórico. Desempate: atendimentos antes de auditorias, depois `id` decrescente.

### Regras complementares (atendimentos)

- **Agenda:** `date_from` e `date_to` inclusivos, até 31 dias; `date_to` antes de `date_from` → `422`. Enviando só uma das datas, a outra vale ±6 dias. Valor desconhecido em `status` → `422`.
- **Profissional:** a identidade ignora maiúsculas, acentos e espaços extras ("Dr. Érico" = "dr. erico"), tanto no conflito de horário quanto no filtro da agenda.
- **Conflito de horário:** intervalos semiabertos (14:00–14:30 e 14:30 não conflitam); atendimentos `cancelled`/`no_show` e de pacientes na lixeira não bloqueiam horário; a própria consulta é ignorada na edição.
- **`scheduled_at` no passado** é aceito (registro retroativo); frações de segundo são descartadas.
- **`PUT` em atendimento final:** `scheduled_at` é comparado como instante; campo opcional omitido conta como `null` e duração omitida como 30; cada campo alterado (exceto `notes`) gera seu próprio erro.
- **`PATCH` para o mesmo status** → `422`.
- **`patient`** também vem nas respostas de `PUT`, `PATCH .../status` e `PUT .../eye-exam`.
- **Prontuário:** `PUT` é substituição completa (corpo vazio cria prontuário vazio); erros por olho usam chaves com ponto (`errors["od.axis"]`); números voltam como números JSON (`-1.0` → `-1`); Snellen sem zero à esquerda (`20/040` é inválido).
- **Receita:** a data do nome do arquivo é a do atendimento; o `422` vem como `errors.eye_exam`; basta um valor de refração em qualquer olho (cilíndrico 0 conta).
- **Linha do tempo:** os registros de histórico são só as alterações do cadastro do paciente; mudanças em atendimentos não geram auditoria.

## Endereço, foto, anexos e logs (Sprint 5)

### Recurso `Address`

```json
{ "cep": "01310100", "street": "Avenida Paulista", "number": "1578", "complement": "Sala 12", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP" }
```

| Campo          | Regras |
|----------------|--------|
| `cep`          | obrigatório, 8 dígitos (aceita máscara `00000-000`; armazenado só com dígitos) |
| `street`       | obrigatório, até 255 |
| `number`       | obrigatório, até 20 (aceita "S/N") |
| `complement`   | opcional, até 100 |
| `neighborhood` | obrigatório, até 100 |
| `city`         | obrigatório, até 100 |
| `state`        | obrigatório, sigla de UF válida (`SP`, `RJ`, ...; aceita minúsculas, armazenada em maiúsculas) |

- `POST /patients` e `PUT /patients/{id}` aceitam `address` (objeto ou `null`). No `PUT` (substituição completa), `address` omitido ou `null` remove o endereço. Erros usam chaves com ponto (`errors["address.cep"]`).
- `street`, `number`, `complement` e `neighborhood` são armazenados **criptografados**; `cep`, `city` e `state` ficam em claro.
- Alterações de endereço entram no histórico do paciente (e na linha do tempo) como campos `address.<campo>`; uma requisição que muda paciente e endereço gera um único registro.
- A exportação CSV ganha as colunas `CEP;Endereço;Cidade;UF` depois de `Descrição` (e antes de `Excluído em` na lixeira); CEP formatado `00000-000`, endereço = "rua, número - complemento - bairro".
- A resposta `200` de `GET /cep/{cep}` pode trazer `street`/`neighborhood` vazios (CEPs de cidade inteira).

### `GET /cep/{cep}`
Consulta de CEP (proxy do ViaCEP, com cache de 24 h). Aceita máscara.
`200 { "data": { "cep": "01310100", "street": "...", "neighborhood": "...", "city": "...", "state": "SP" } }` ·
`404 { "message": "CEP não encontrado." }` · `422` CEP com formato inválido · `503 { "message": "Serviço de CEP indisponível. Preencha o endereço manualmente." }`.

### Foto do paciente
- `POST /patients/{id}/photo` (multipart, campo `photo`): JPEG, PNG ou WebP, até 2 MB, entre 100×100 e 4000×4000 px. A imagem é **reprocessada no servidor** (corte quadrado centralizado, máx. 512×512, JPEG), o que remove metadados EXIF (como localização). `200 { "data": Patient }` · `422`.
- `GET /patients/{id}/photo`: `image/jpeg`, `Cache-Control: private, max-age=86400`. `404` se não houver foto.
- `DELETE /patients/{id}/photo`: `204` · `404` se não houver foto.
- Pacientes na lixeira: as três rotas retornam `404` (`{ "message": "Foto não encontrada." }` quando não há foto).
- Histórico: trocar ou remover a foto gera um evento `updated` com `changes: { "photo": { "old": null, "new": "adicionada" | "alterada" | "removida" } }`, nunca o conteúdo da imagem.
- `GET .../photo` conta no limite de requisições da API (mitigado pelo `?v=` com `max-age=86400`).

### Anexos de exames (por atendimento)

Recurso `Attachment`:
```json
{ "id": 3, "consultation_id": 12, "original_name": "topografia.pdf", "mime_type": "application/pdf", "size": 183245, "description": "Topografia de córnea", "download_url": "http://localhost:8000/api/attachments/3/download", "created_at": "2026-11-03T15:10:00-03:00" }
```

- `GET /consultations/{id}/attachments`: lista (mais recente primeiro, sem paginação).
- `POST /consultations/{id}/attachments` (multipart): `file` obrigatório (PDF, JPEG ou PNG, até 10 MB; o tipo é conferido pelo conteúdo, não só pela extensão), `description` opcional (até 255). Qualquer status de atendimento. `201 { "data": Attachment }` · `422`.
- `GET /attachments/{id}/download`: o arquivo original, com `Content-Disposition: attachment; filename="<nome ASCII>"; filename*=UTF-8''<nome UTF-8>`. O `original_name` é saneado e recebe a extensão do tipo detectado quando não bate (ex.: `scan.jpg` que é PNG vira `scan.jpg.png`).
- Uploads acima de 12 MB: `413 { "message": "O arquivo enviado é grande demais." }`.
- `DELETE /attachments/{id}`: `204`.
- Arquivos e fotos ficam em disco **privado** (fora de `public/`) e **criptografados**; o nome no disco é aleatório. `404 { "message": "Anexo não encontrado." }`; atendimentos de pacientes na lixeira → `404`.

### Logs e rastreabilidade
- Toda resposta traz `X-Request-Id` (exposto via CORS): o valor recebido no header de mesmo nome (se for um UUID ou até 64 caracteres `[A-Za-z0-9-_.]`) ou um UUID gerado.
- Cada requisição gera uma linha de log com `request_id`, método, **rota** (sem query string, que pode conter CPF), status e duração. Em produção os logs saem em JSON no stderr.

## Erro de validação (422)
Formato padrão do Laravel, mensagens em pt-BR:
```json
{
  "message": "O campo nome completo é obrigatório. (and 2 more errors)",
  "errors": { "name": ["O campo nome completo é obrigatório."], "cpf": ["O CPF informado é inválido."] }
}
```

## CORS
Liberado em `api/*` para as origens de `CORS_ALLOWED_ORIGINS` (separadas por vírgula). Padrão:
`http://localhost:5173,http://127.0.0.1:5173` (front em dev); o compose de produção usa a porta do front
(`http://localhost:8080`).
