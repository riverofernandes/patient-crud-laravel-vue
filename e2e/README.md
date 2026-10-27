# Testes E2E (Playwright)

Testes de ponta a ponta do CRUD de pacientes rodando no Chromium contra a aplicação real (front + API + MySQL).

## Rodando localmente

Com a stack no ar e um limite de requisições folgado (a suíte faz cerca de 425 por execução; o padrão
da API é 300/min e as requisições do browser não são repetidas em caso de 429):

```bash
API_RATE_LIMIT=1000 docker compose up -d   # na raiz
```

```bash
cd e2e
npm ci
npx playwright install chromium   # primeira vez (no Linux: --with-deps)
npx playwright test               # headless
npx playwright test --ui          # modo interativo
npx playwright show-report        # relatório HTML (playwright-report/)
```

| Variável       | Padrão                      | Uso                                   |
|----------------|-----------------------------|---------------------------------------|
| `E2E_BASE_URL` | `http://localhost:5173`     | Front-end                             |
| `E2E_API_URL`  | `http://localhost:8000/api` | API (criação/limpeza dos dados)       |

Se a API estiver em outra porta (ex.: `API_PORT=8001 API_RATE_LIMIT=1000 docker compose up -d`):

```bash
E2E_API_URL=http://localhost:8001/api npx playwright test
```

## Como os testes são escritos

- Cada teste cria os próprios pacientes pela API (CPF válido e nome únicos gerados em `support/data.ts`)
  e os exclui ao final (fixture `api` em `support/fixtures.ts`), então rodam em qualquer banco, inclusive
  o semeado, e podem ser repetidos.
- A exclusão é *soft delete* e a API não tem exclusão definitiva: os pacientes dos testes terminam na
  lixeira (que cresce a cada execução local; no CI o banco é descartado). Por isso os testes da lixeira
  sempre filtram por um nome único. Pacientes restaurados durante o teste (pela UI ou por
  `api.restorePatient`) voltam a ser excluídos na limpeza; quem excluir com `api.deletePatient` e depois
  restaurar pela UI deve chamar `api.track(id)` de novo.
- Localizadores por papel/rótulo e textos em pt-BR, como aparecem na interface.
- O fuso é `America/Sao_Paulo` para pegar erros de "um dia a menos" em datas.
- O socket de HMR do Vite é isolado durante os testes, então edições salvas enquanto a suíte roda não
  recarregam a página.
- A API limita as requisições por IP (browser e testes somam; padrão 300/min) e uma execução completa
  usa cerca de 425, em menos de um minuto. Suba a API com um limite maior antes de rodar a suíte
  inteira (o CI usa 1000):

  ```bash
  API_RATE_LIMIT=1000 docker compose up -d api          # ou API_PORT=8001 API_RATE_LIMIT=1000 ...
  ```

  O helper da API repete em caso de 429, mas as requisições do browser não. Crie só os pacientes de
  que o teste precisa. O cabeçalho `X-RateLimit-Limit`/`X-RateLimit-Remaining` de qualquer
  `GET /api/patients` mostra o limite e o saldo.
- Filtros (`filters.spec.ts`) e exportação (`export.spec.ts`) sempre combinam o filtro com a busca pelo
  prefixo único do teste, porque dados semeados e de outros testes também batem com qualquer sexo/idade.
  As idades-limite usam datas calculadas no fuso de São Paulo (`birthDateYearsAgo`/`ageOn` em
  `support/fixtures.ts`).
- A exportação é verificada pelo evento de download do Playwright: nome do arquivo, bytes (BOM UTF-8),
  cabeçalho, separador `;`, CRLF, valores formatados e proteção contra fórmulas.
- O histórico (`history.spec.ts`) cria auditorias pela UI e pela API (`api.updatePatient`,
  `api.audits`).

No CI (`.github/workflows/ci.yml`, job `e2e`) a stack sobe com `docker compose up -d --build` e o relatório
é publicado como artefato `playwright-report` quando há falha.
