# Roadmap — melhorias e novas funcionalidades

Plano de evolução do CRUD de pacientes, organizado em fases. Cada item traz o **valor** que agrega,
o **esforço** estimado (P = horas, M = 1–2 dias, G = 3+ dias) e o que precisa mudar.

## Visão geral

| Fase | Foco                          | Objetivo                                                   |
|------|-------------------------------|------------------------------------------------------------|
| 1    | Qualidade e entrega           | Provar que o código funciona a cada mudança                |
| 2    | Funcionalidades do cadastro   | Tornar o CRUD mais útil no dia a dia de uma clínica        |
| 3    | Segurança e LGPD              | Dados de saúde são sensíveis: acesso controlado e auditado |
| 4    | Domínio clínico (EyeCare)     | Sair do cadastro e entrar no atendimento oftalmológico     |
| 5    | Arquitetura e infraestrutura  | Preparar para produção e crescimento                       |

---

## Fase 1 — Qualidade e entrega

| # | Item | Valor | Esforço | Como |
|---|------|-------|---------|------|
| 1.1 | ✅ **CI no GitHub Actions** | Selo verde no README; nenhum push quebra o projeto | P | Workflow com jobs `api` (composer install, `php artisan test`, `pint --test`) e `web` (`npm ci`, lint, `npm test`, build) |
| 1.2 | ✅ **Testes E2E com Playwright** | Critério de desempate do desafio; cobre o fluxo real | M | Pasta `e2e/` na raiz: cadastrar → buscar → ver → editar → excluir, com dados criados e limpos pelo próprio teste; job no CI subindo o `docker compose` |
| 1.3 | **Testes de componentes no front** | Hoje só os utilitários têm teste | M | `@vue/test-utils` v1: `PatientForm` (obrigatórios, erros 422), `ConfirmDeleteModal`, `PatientListView` (busca com debounce, paginação) |
| 1.4 | **Cobertura de testes** | Mostra o quanto do código está protegido | P | `php artisan test --coverage` (PCOV no Dockerfile) e `vitest --coverage`; badge no README |
| 1.5 | **Análise estática** | Pega erros antes de rodar | P | Larastan (PHPStan) nível 6+ no back; `vue-tsc` já roda no build |
| 1.6 | ✅ **Dependabot / Renovate** | Dependências atualizadas com segurança | P | `.github/dependabot.yml` para composer, npm e Docker |
| 1.7 | ✅ **Ajustes de acessibilidade pendentes** | Leitor de tela anuncia o botão do calendário como "Nenhuma data selecionada" | P | `label-*` do `b-form-datepicker` em pt-BR; revisar contraste e foco com axe |

## Fase 2 — Funcionalidades do cadastro

| # | Item | Valor | Esforço | Como |
|---|------|-------|---------|------|
| 2.1 | **Lixeira e restauração** | Aproveita o soft delete: exclusão por engano tem volta | M | `GET /patients?trashed=only`, `POST /patients/{id}/restore` (validando CPF único ao restaurar); tela "Pacientes excluídos" |
| 2.2 | **Ordenação por coluna** | Achar pacientes mais rápido | P | Query `sort=name\|birth_date\|created_at` e `direction` com lista branca no back; cabeçalhos clicáveis na tabela |
| 2.3 | **Filtros** | Recortes como "mulheres acima de 60 anos" | M | `sex`, `age_min`/`age_max` (convertidos em faixa de `birth_date`); painel de filtros sincronizado com a URL |
| 2.4 | **Itens por página** | Controle do usuário sobre a listagem | P | Select 10/25/50 ligado ao `per_page` que a API já aceita |
| 2.5 | **Idade calculada** | Informação mais usada no atendimento | P | Campo `age` no `PatientResource`; exibir na listagem e nos detalhes |
| 2.6 | **Endereço com busca de CEP** | Cadastro completo | M | Campos de endereço (tabela própria `addresses` 1:1); autopreenchimento via ViaCEP no front |
| 2.7 | **Verificação de CPF em tempo real** | Avisa duplicidade antes de salvar | P | `GET /patients/check-cpf?cpf=` chamado no blur do campo |
| 2.8 | **Foto do paciente** | Identificação visual na recepção | M | Upload com validação de tipo/tamanho, armazenamento no `storage` (S3/MinIO depois); avatar com iniciais como fallback |
| 2.9 | **Exportar CSV** | Relatórios e integração com planilhas | P | `GET /patients/export?search=...` respeitando filtros, com stream de resposta |

## Fase 3 — Segurança e LGPD

| # | Item | Valor | Esforço | Como |
|---|------|-------|---------|------|
| 3.1 | **Autenticação** | Hoje a API é aberta | M | Laravel Sanctum (SPA com cookie): login, logout, recuperação de senha; guarda de rotas no Vue Router |
| 3.2 | **Perfis e permissões** | Recepção não precisa excluir; médico vê prontuário | M | Papéis `admin`, `recepcao`, `medico` com Policies no Laravel; esconder ações sem permissão no front |
| 3.3 | **Histórico de alterações (auditoria)** | Quem mudou o quê e quando — exigência comum em saúde | M | `owen-it/laravel-auditing` ou tabela `patient_audits`; aba "Histórico" nos detalhes |
| 3.4 | **Criptografia de dados sensíveis** | Proteção em caso de vazamento do banco | G | Casts `encrypted` para CPF/RG/telefone + coluna de hash (blind index) para manter a busca por CPF |
| 3.5 | **Mascaramento por perfil** | Princípio do menor privilégio | P | CPF parcial (`***.189.530-**`) na listagem para perfis sem necessidade |
| 3.6 | **Log de acesso a prontuários** | Rastreabilidade exigida pela LGPD | M | Registrar visualizações de detalhes por usuário |

## Fase 4 — Domínio clínico (EyeCare)

| # | Item | Valor | Esforço | Como |
|---|------|-------|---------|------|
| 4.1 | **Agendamento de consultas** | Primeiro passo do atendimento | G | Entidades `appointments` e `professionals`; calendário semanal; status (agendada, confirmada, realizada, faltou) |
| 4.2 | **Prontuário oftalmológico** | Diferencial forte para o domínio da empresa | G | Registro por consulta: acuidade visual, refração (esférico, cilíndrico, eixo, adição) para OD e OE, pressão intraocular, observações |
| 4.3 | **Receita de óculos em PDF** | Entregável direto ao paciente | M | Gerar PDF a partir da refração (DomPDF) com dados do profissional |
| 4.4 | **Anexos de exames** | Centraliza laudos e imagens | M | Upload por consulta com pré-visualização de PDF/imagem |
| 4.5 | **Linha do tempo do paciente** | Visão única do histórico | M | Na tela de detalhes: consultas, exames e alterações em ordem cronológica |
| 4.6 | **Lembretes de consulta** | Reduz faltas | M | Filas (Redis + Horizon) disparando e-mail/WhatsApp 24h antes |

## Fase 5 — Arquitetura e infraestrutura

| # | Item | Valor | Esforço | Como |
|---|------|-------|---------|------|
| 5.1 | ✅ **Documentação OpenAPI** | API autodocumentada, complementa o Postman | P | Scribe gerando a doc a partir dos FormRequests e Resources, publicada em `/docs` |
| 5.2 | **Camada de serviço / actions** | Regras fora do controller, mais fáceis de testar | M | `CreatePatient`, `UpdatePatient`, `DeletePatient` como Actions; controller só orquestra |
| 5.3 | ✅ **Front em modo produção** | Hoje o container roda o Vite de desenvolvimento | P | Dockerfile multi-stage (build → nginx) e `docker-compose.prod.yml`; `APP_DEBUG=false` |
| 5.4 | 🟡 **Observabilidade** (health check feito) | Saber quando algo quebra em produção | P | Sentry no back e no front; logs estruturados em JSON; endpoint `/api/health` |
| 5.5 | **Cache e performance** | Listagem rápida com muitos registros | M | Índice FULLTEXT ou Meilisearch/Scout para busca por nome; Redis para cache |
| 5.6 | **Deploy com demo pública** | Avaliador testa sem instalar nada | M | Render/Fly.io/Railway com deploy automático pelo CI a partir da `main` |
| 5.7 | **Migração para Vue 3** | Vue 2 está sem suporte desde 31/12/2023 | G | Vue 3 + Vite atual + Pinia, trocando BootstrapVue por Bootstrap-Vue-Next ou Vuetify 3 (fora do escopo do desafio, que exige Vue 2) |

---

## Ordem recomendada

1. ✅ **Sprint 1 — vitrine do desafio (2–3 dias):** concluída, exceto a demo pública (adiada) — 1.1 CI · 1.2 E2E · 1.7 acessibilidade · 5.1 OpenAPI · 5.3 front em produção · 5.6 demo pública
2. **Sprint 2 — cadastro completo (3–4 dias):** 2.1 lixeira · 2.2 ordenação · 2.4 itens por página · 2.5 idade · 2.7 CPF em tempo real · 1.3 testes de componentes
3. **Sprint 3 — segurança (4–5 dias):** 3.1 autenticação · 3.2 perfis · 3.3 auditoria · 1.5 Larastan
4. **Sprint 4+ — produto clínico:** 4.1 agendamento · 4.2 prontuário · 4.5 linha do tempo · 4.3 receita em PDF

A Sprint 1 é a que mais pesa numa avaliação técnica: mostra automação, testes de ponta a ponta e um
link funcionando, sem mudar o escopo pedido.
