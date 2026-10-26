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

Formatação visual (máscaras de CPF/RG/celular, data `DD/MM/AAAA`) é responsabilidade do front-end.

Exclusão é **soft delete** (`deleted_at`). Pacientes excluídos não aparecem na listagem e retornam 404 no detalhe. Um CPF de paciente excluído pode ser reutilizado.

## Endpoints

### `GET /patients`
Query params: `search` (opcional — busca por nome parcial ou CPF, com ou sem máscara), `page` (default 1), `per_page` (default 10, máx 100).
Ordenação: `name` asc.

```json
200
{
  "data": [ { "id": 1, "name": "...", "cpf": "62518953060", "rg": "356880199", "birth_date": "1996-07-20", "sex": "M", "phone": "11928535718", "email": null, "description": null, "created_at": "...", "updated_at": "..." } ],
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
