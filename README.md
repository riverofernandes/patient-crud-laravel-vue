# Cadastro de Pacientes — Laravel + Vue 2

Aplicação fullstack com CRUD completo de pacientes, desenvolvida para o desafio
[eyecarehealth/bi-tech-interview](https://github.com/eyecarehealth/bi-tech-interview).

| Camada     | Stack                                                                 |
|------------|-----------------------------------------------------------------------|
| Back-end   | PHP 8.2, Laravel 10, MySQL 8 — API REST (`php-laravel/`)              |
| Front-end  | Vue 2.7, Vue Router 3, BootstrapVue 2 (Bootstrap 4), axios, Vite (`front-vuejs/`) |
| Infra      | Docker Compose na raiz (MySQL + API + front)                          |

## Como rodar (Docker — recomendado)

Pré-requisito: Docker com Docker Compose v2.

```bash
docker compose up -d --build
```

Na primeira subida (≈1–2 min) o container da API instala as dependências do Composer, cria o `.env`,
gera a `APP_KEY`, aguarda o MySQL, roda as migrations e popula o banco com 30 pacientes de exemplo
(o seed só roda com a tabela vazia, então reiniciar não duplica dados). O container `web` roda `npm ci`
e sobe o Vite.

| Serviço   | URL                              |
|-----------|----------------------------------|
| Front-end | http://localhost:5173            |
| API       | http://localhost:8000/api        |

Acompanhar os logs: `docker compose logs -f api web` · Parar: `docker compose down` (com `-v` apaga o banco).

### Portas ocupadas?

As portas do host podem ser trocadas por variáveis de ambiente:

```bash
API_PORT=8001 WEB_PORT=5174 docker compose up -d --build
```

O front recebe automaticamente `VITE_API_URL=http://localhost:${API_PORT}/api`. Se mudar `WEB_PORT`,
inclua a nova origem em `CORS_ALLOWED_ORIGINS` no `php-laravel/.env` (por padrão são liberadas
`http://localhost:5173` e `http://127.0.0.1:5173`). O MySQL não é exposto no host (veja o comentário no
`docker-compose.yml` para habilitar).

## Rodando o front-end fora do Docker (opcional)

Com a API rodando no Docker:

```bash
cd front-vuejs
cp .env.example .env.local   # ajuste VITE_API_URL se a API não estiver na 8000
npm install
npm run dev
```

## Testes e qualidade

```bash
docker compose exec api php artisan test        # testes de feature + unitários (SQLite em memória)
docker compose exec api ./vendor/bin/pint --test # padrão de código PSR-12
```

```bash
cd front-vuejs && npm test         # Vitest (máscaras, datas, validação de CPF)
cd front-vuejs && npm run lint     # ESLint + Prettier
cd front-vuejs && npm run build    # type-check (vue-tsc) + build de produção
```

Os testes do back-end usam SQLite em memória e nunca tocam o banco MySQL do container.

## Postman

Coleção: [`php-laravel/postman/patients.postman_collection.json`](php-laravel/postman/patients.postman_collection.json).
Importe no Postman; a variável `baseUrl` aponta para `http://localhost:8000/api`. A requisição de criação
salva o `id` em `patientId`, usado por detalhe, edição e exclusão. Também roda via linha de comando:

```bash
npx newman run php-laravel/postman/patients.postman_collection.json
```

## API

Contrato completo em [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md).

| Método | Rota                  | Descrição                                                        |
|--------|-----------------------|------------------------------------------------------------------|
| GET    | `/api/patients`       | Lista paginada (`search` por nome ou CPF, `page`, `per_page`)    |
| GET    | `/api/patients/{id}`  | Detalhe                                                          |
| POST   | `/api/patients`       | Cria (201)                                                       |
| PUT    | `/api/patients/{id}`  | Atualiza (substituição completa)                                 |
| DELETE | `/api/patients/{id}`  | **Soft delete** (204) — o registro permanece com `deleted_at`    |

Validação via FormRequest com mensagens em pt-BR: CPF com dígitos verificadores e único entre pacientes
ativos (o CPF de um paciente excluído pode ser reutilizado), data de nascimento não futura, sexo `M`/`F`,
e-mail opcional. CPF, RG e celular aceitam máscara na entrada e são armazenados só com dígitos.

## Telas

| Rota                     | Tela                                                                 |
|--------------------------|----------------------------------------------------------------------|
| `/`                      | Listagem com busca (debounce), paginação e ações ver / editar / excluir |
| `/pacientes/novo`        | Cadastro                                                             |
| `/pacientes/:id`         | Detalhes                                                             |
| `/pacientes/:id/editar`  | Edição                                                               |

A exclusão abre um modal de confirmação a partir da listagem, dos detalhes ou da edição. O layout segue os
protótipos em [`images/`](images); a tela de detalhes não tinha protótipo e foi desenhada no mesmo estilo.

## Estrutura

```
docker-compose.yml          MySQL + API + front
docs/API_CONTRACT.md        contrato da API
php-laravel/
  app/Http/Controllers/PatientController.php
  app/Http/Requests/PatientRequest.php   validação + normalização de máscaras
  app/Http/Resources/PatientResource.php
  app/Rules/Cpf.php                      validação de CPF
  app/Models/Patient.php                 SoftDeletes + scope de busca
  database/                              migration, factory, seeder
  tests/                                 Feature (API) e Unit (regra de CPF)
  docker/entrypoint.sh                   bootstrap do container
  postman/                               coleção do Postman
front-vuejs/src/
  services/      cliente HTTP e serviço de pacientes (tipado)
  components/    layout, formulário reutilizado em cadastro/edição, modal de exclusão
  views/         listagem, cadastro, detalhes, edição, não encontrado
  utils/         máscaras, conversão de datas, validação, toasts
```
