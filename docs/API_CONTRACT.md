# Contrato da API — Pacientes

Base URL (dev): `http://localhost:8000/api`
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
