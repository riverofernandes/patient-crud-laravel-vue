# Cadastro de Pacientes — Laravel + Vue 2

[![CI](https://github.com/riverofernandes/patient-crud-laravel-vue/actions/workflows/ci.yml/badge.svg)](https://github.com/riverofernandes/patient-crud-laravel-vue/actions/workflows/ci.yml)

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
| Docs da API (OpenAPI) | http://localhost:8000/docs |
| Health check | http://localhost:8000/api/health |

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

A API limita a 300 requisições por minuto por cliente; `API_RATE_LIMIT` ajusta o valor (o CI usa 2000
para a suíte E2E). As fotos dos pacientes têm limite próprio (`PHOTO_RATE_LIMIT`, padrão 1200/min), para
que uma listagem cheia de avatares não esgote o limite da API.

> Já tinha o projeto rodando antes desta versão? Rode `docker compose exec api composer install`
> depois de atualizar, pois o container só instala dependências quando `vendor/` não existe.

## Ambiente de produção (Docker)

`docker-compose.prod.yml` sobe imagens imutáveis, sem bind mount e com debug desligado:

- **API:** `php-laravel/Dockerfile` (target `prod`): nginx + php-fpm sob supervisord, usuário sem
  privilégios, `composer install --no-dev`, opcache e caches de config/rotas/views.
- **Front:** `front-vuejs/Dockerfile`: build do Vite servido por nginx, com fallback de rotas do SPA,
  gzip, cache longo para `/assets` e headers de segurança.

```bash
APP_KEY=base64:$(openssl rand -base64 32) SEED_ON_START=true docker compose -f docker-compose.prod.yml up -d --build
```

| Serviço   | URL                       |
|-----------|---------------------------|
| Front-end | http://localhost:8080     |
| API       | http://localhost:8000/api |

Variáveis: `APP_KEY` (obrigatória), `API_PORT`, `WEB_PORT`, `DB_PASSWORD`, `SEED_ON_START` (popula o
banco na subida). O projeto de produção usa outro nome (`patients-prod`), então não conflita com o
banco de desenvolvimento.

> **Atenção à `APP_KEY` em produção:** CPF, RG, celular, endereço, dados clínicos, fotos e anexos são
> gravados criptografados com essa chave. Trocar a chave torna esses dados ilegíveis. Fotos e anexos ficam
> no volume `api-private`: faça backup dele junto com o banco e a `APP_KEY`. Faça backup do banco antes do primeiro deploy desta versão:
> a migration criptografa os dados existentes.

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
docker compose exec api php artisan test                    # testes de feature + unitários (SQLite em memória)
docker compose exec api php artisan test --coverage --min=80 # com cobertura (pcov), mínimo de 80%
docker compose exec api ./vendor/bin/phpstan analyse         # análise estática (Larastan, nível 8)
docker compose exec api ./vendor/bin/pint --test             # padrão de código PSR-12
```

```bash
cd front-vuejs && npm test               # Vitest: utilitários, serviços e componentes
cd front-vuejs && npm run test:coverage  # com cobertura (Istanbul) e limites mínimos
cd front-vuejs && npm run lint     # ESLint + Prettier
cd front-vuejs && npm run build    # type-check (vue-tsc) + build de produção
```

Os testes do back-end usam SQLite em memória e nunca tocam o banco MySQL do container.

### Testes E2E

Testes de ponta a ponta com Playwright em [`e2e/`](e2e/README.md): listagem, busca (CPF só completo),
filtros por sexo e idade, ordenação, paginação, cadastro, detalhes, edição, exclusão, lixeira, desfazer,
exportação CSV, histórico de alterações, endereço com CEP, foto, anexos, X-Request-Id, layout mobile e
auditoria de acessibilidade com axe (WCAG 2.1 AA) em todas as telas. Cada teste cria e apaga os próprios
pacientes via API, e a consulta de CEP é sempre simulada (`page.route`), sem chamar o ViaCEP.

A suíte (124 testes, incluindo agenda, atendimentos, prontuário, receita em PDF e linha do tempo) faz
cerca de 860 requisições por execução (pico de ~780 por minuto), acima do limite padrão da API (300/min), então suba
a stack com o limite maior:

```bash
cd e2e && npm ci && npx playwright install chromium
```

```bash
API_RATE_LIMIT=1000 docker compose up -d
```

```bash
cd e2e && npx playwright test
```

Se a API subiu em outra porta: `API_PORT=8001 API_RATE_LIMIT=1000 docker compose up -d` e
`E2E_API_URL=http://localhost:8001/api npx playwright test`.
Relatório: `npx playwright show-report`.

### Integração contínua

O workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) roda a cada push na `main` e em todo PR:
análise estática (Larastan), testes com cobertura mínima de 80% (pcov) e pint do back-end; lint, testes
com cobertura (limites no `vite.config.ts`, relatório no resumo do job e artefato `web-coverage`) e build
do front-end; por fim, confere que as migrations rodaram no banco novo e executa a suíte E2E contra a
stack do `docker compose`. O Dependabot mantém as dependências de Composer, npm, Docker e Actions atualizadas.

### Acessibilidade

As telas passam sem violações na auditoria axe (WCAG 2.1 AA), em desktop e em 375px: contraste,
rótulos e erros ligados aos campos, foco movido para o título a cada navegação, link "pular para o
conteúdo" e modal com foco preso e devolvido.

## Postman

Coleção: [`php-laravel/postman/patients.postman_collection.json`](php-laravel/postman/patients.postman_collection.json).
Importe no Postman; a variável `baseUrl` aponta para `http://localhost:8000/api`. A requisição de criação
salva o `id` em `patientId`, usado por detalhe, edição e exclusão. Os uploads (foto e anexos) usam os
arquivos de `php-laravel/postman/fixtures/`, por isso o `--working-dir`. Também roda via linha de comando:

```bash
npx newman run php-laravel/postman/patients.postman_collection.json --working-dir php-laravel/postman
```

## API

Contrato completo em [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md). Documentação interativa gerada
com [Scribe](https://scribe.knuckles.wtf) em `/docs`, com a especificação OpenAPI em `/docs.openapi` e
uma coleção Postman gerada em `/docs.postman`. A documentação é versionada; para regenerar após mudar
a API:

```bash
docker compose exec -e APP_URL=http://localhost:8000 api php artisan scribe:generate
```

O `APP_URL` fixo mantém a URL base dos arquivos gerados estável mesmo que a stack rode em outra porta.

| Método | Rota                  | Descrição                                                        |
|--------|-----------------------|------------------------------------------------------------------|
| GET    | `/api/patients`       | Lista paginada (`search`, `page`, `per_page`, `sort`, `direction`, `trashed=only`, `sex`, `age_min`, `age_max`) |
| GET    | `/api/patients/export` | Exporta em CSV com os mesmos filtros (UTF-8 com BOM, `;`)        |
| GET    | `/api/patients/{id}/audits` | Histórico de alterações do paciente                         |
| GET    | `/api/patients/{id}`  | Detalhe                                                          |
| POST   | `/api/patients`       | Cria (201)                                                       |
| PUT    | `/api/patients/{id}`  | Atualiza (substituição completa)                                 |
| DELETE | `/api/patients/{id}`  | **Soft delete** (204) — o registro permanece com `deleted_at`    |
| POST   | `/api/patients/{id}/restore` | Restaura um paciente da lixeira (409 se o CPF já estiver em uso) |
| GET    | `/api/patients/cpf-availability` | Verifica se um CPF está livre (`cpf`, `ignore`)         |
| GET    | `/api/patients/{id}/consultations` | Atendimentos do paciente (POST cria)                 |
| GET    | `/api/consultations`  | Agenda por período (`date_from`, `date_to`, `status`, `professional`) |
| GET    | `/api/consultations/{id}` | Detalhe do atendimento (PUT edita)                           |
| PATCH  | `/api/consultations/{id}/status` | Muda o status (agendado → confirmado → realizado; falta; cancelado) |
| PUT    | `/api/consultations/{id}/eye-exam` | Grava o prontuário oftalmológico (só em atendimento realizado) |
| GET    | `/api/consultations/{id}/prescription` | Receita de óculos em PDF                             |
| GET    | `/api/patients/{id}/timeline` | Linha do tempo: atendimentos e alterações do cadastro       |
| GET    | `/api/cep/{cep}`      | Consulta de CEP (proxy do ViaCEP com cache de 24 h)              |
| POST   | `/api/patients/{id}/photo` | Envia a foto do paciente (GET devolve, DELETE remove)       |
| GET    | `/api/consultations/{id}/attachments` | Anexos de exames do atendimento (POST envia)     |
| GET    | `/api/attachments/{id}/download` | Baixa um anexo (DELETE remove)                        |
| GET    | `/api/health`         | Health check com verificação do banco (200 / 503)                |

Validação via FormRequest com mensagens em pt-BR: CPF com dígitos verificadores e único entre pacientes
ativos (o CPF de um paciente excluído pode ser reutilizado), data de nascimento não futura, sexo `M`/`F`,
e-mail opcional. CPF, RG e celular aceitam máscara na entrada e são armazenados só com dígitos.

**Dados sensíveis:** CPF, RG e celular ficam **criptografados** no banco (casts `encrypted` do Laravel,
com a `APP_KEY`). Para manter a unicidade e a busca por CPF sem descriptografar a tabela, a API guarda
um hash HMAC do CPF (*blind index*), com chave definida em `CPF_HASH_KEY` (derivada da `APP_KEY` se vazia).
A busca por CPF completo usa esse hash; a busca por **qualquer trecho do CPF** (3 ou mais dígitos)
descriptografa os CPFs na API para comparar, o que tem custo proporcional ao número de pacientes (adequado
para milhares de registros). Se a chave do hash
mudar, rode `php artisan patients:rehash-cpf`.

**Atendimentos e prontuário:** cada atendimento tem status (Agendado, Confirmado, Realizado, Faltou,
Cancelado) com transições validadas e impede dois horários sobrepostos para o mesmo profissional (que,
sem login, é um nome e CRM em texto livre). Em atendimentos realizados registra-se o prontuário
oftalmológico por olho (acuidade visual sem/com correção, esférico, cilíndrico, eixo, adição e pressão
intraocular, com as regras clínicas de cada campo) e gera-se a receita de óculos em PDF (DomPDF). Queixa,
anotações, diagnóstico e observações ficam criptografados. A agenda trabalha com dias de São Paulo, e o
seed inclui atendimentos de demonstração na semana corrente.

**Endereço, foto e anexos:** o cadastro aceita endereço opcional, com preenchimento pelo CEP (a API
consulta o ViaCEP e guarda o resultado por 24 h). A foto do paciente é reprocessada no servidor (recorte
quadrado, até 512 px, JPEG), o que remove metadados como a localização. Atendimentos aceitam anexos de
exames (PDF, JPEG ou PNG até 10 MB, tipo conferido pelo conteúdo). Fotos e anexos ficam em disco privado,
criptografados e com nome aleatório.

**Logs:** toda resposta traz `X-Request-Id`, e cada requisição gera uma linha de log com método, rota,
status e duração, sem a query string (que pode conter CPF). Em produção os logs saem em JSON no stderr;
em desenvolvimento vão para `storage/logs/laravel.log`.

**Histórico:** cada cadastro, edição (só os campos alterados, com antes e depois), exclusão e restauração
fica registrado em `patient_audits`, também criptografado. Como a API é aberta nesta fase, o registro não
tem usuário (`user_id` nulo, pronto para quando houver login).

## Telas

| Rota                     | Tela                                                                 |
|--------------------------|----------------------------------------------------------------------|
| `/`                      | Listagem com busca (debounce), filtros (sexo, faixa de idade), ordenação por coluna, itens por página, idade, exportação CSV e ações ver / editar / excluir |
| `/pacientes/novo`        | Cadastro                                                             |
| `/pacientes/:id`         | Detalhes, com as abas Dados, Histórico, Atendimentos e Linha do tempo (`?aba=`) |
| `/pacientes/:id/atendimentos/novo` | Novo atendimento                                          |
| `/atendimentos/:id`      | Atendimento: status, edição, prontuário oftalmológico e receita em PDF |
| `/agenda`                | Agenda semanal com filtros por status e profissional               |
| `/pacientes/:id/editar`  | Edição                                                               |
| `/pacientes/lixeira`     | Pacientes excluídos, com busca, exportação CSV e ação de restaurar   |

A exclusão abre um modal de confirmação a partir da listagem, dos detalhes ou da edição. Como é um soft
delete, o aviso de sucesso traz um botão **Desfazer**, e o paciente fica na **Lixeira**, de onde pode ser
restaurado. No formulário, o CPF é verificado ao sair do campo, avisando de duplicidade antes de salvar.

O layout segue os protótipos em [`images/`](images); as telas de detalhes e da lixeira não tinham protótipo
e foram desenhadas no mesmo estilo.

## Estrutura

```
docker-compose.yml          MySQL + API + front (desenvolvimento)
docker-compose.prod.yml     imagens de produção (nginx + php-fpm, front com nginx)
.github/workflows/ci.yml    CI: back-end, front-end e E2E
docs/API_CONTRACT.md        contrato da API
docs/ROADMAP.md             plano de evolução
e2e/                        testes E2E e de acessibilidade (Playwright + axe)
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
