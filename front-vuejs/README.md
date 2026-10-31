# front-vuejs — Cadastro de pacientes

Vue 2.7 + Vue Router 3 + Vite 3 + TypeScript, BootstrapVue 2 (Bootstrap 4) with SCSS overrides, axios.

## Setup

```sh
cp .env.example .env.local   # VITE_API_URL (default http://localhost:8000/api)
npm install
npm run dev                  # http://localhost:5173 (listens on all interfaces)
```

| Script | |
|---|---|
| `npm run dev` | Dev server (port 5173, `host: true`, works inside a container) |
| `npm run build` | Type-check (vue-tsc) + production build |
| `npm run lint` | ESLint + Prettier (auto-fix) |
| `npm run test` | Vitest: unit tests for `src/utils`/`src/services` + component tests (`@vue/test-utils` v1, jsdom) |
| `npm run test:coverage` | Same, with Istanbul coverage (`coverage/`: text, HTML, lcov) and minimum thresholds set in `vite.config.ts` |

## Structure

```
src/
  services/       api.ts (axios instance + error helpers), patientService.ts (typed CRUD),
                  consultationService.ts (consultations, agenda, status, eye exam, prescription PDF, timeline),
                  cepService.ts (GET /cep), attachmentService.ts (exam attachments: list, upload, download,
                  delete); uploads are multipart with progress (multipartConfig in api.ts)
  composables/    usePatient.ts (load one patient: loading / 404 / error)
  components/     AppLayout, AppBreadcrumb, PatientForm, FormField, DetailItem,
                  ConfirmDeleteModal, PageState, PatientFilters, ExportCsvButton, PatientHistory,
                  PatientConsultations, PatientTimeline, ConsultationForm, ConsultationStatusActions,
                  StatusBadge, EyeExamForm, EyeExamView, DiopterInput, AddressFields (address + CEP
                  lookup), PatientAvatar, PatientPhoto, ConsultationAttachments, UploadProgress
  views/          PatientListView, PatientCreateView, PatientDetailView, PatientEditView, NotFoundView,
                  AgendaView, ConsultationCreateView, ConsultationDetailView
  utils/          format.ts (CPF/RG/phone/date masks, DD/MM/AAAA <-> YYYY-MM-DD, age, date-time),
                  validation.ts (CPF check digits, e-mail), notify.ts (toasts, optional action),
                  restore.ts (restore from trash + toast), debounce.ts,
                  filters.ts (sex/age filters <-> query, validation), download.ts (CSV filename + save),
                  audit.ts (history labels/formatting), datetime.ts (America/Sao_Paulo dates, week math),
                  consultationStatus.ts (labels, transitions, actions), eyeExam.ts (diopters, rules),
                  agenda.ts (?semana/?status/?profissional), timeline.ts (keys, merge, day groups),
                  address.ts (UFs, CEP mask, validation, payload, one-line format),
                  files.ts (file size "1,2 MB", photo/attachment checks, initials)
  test/           helpers for component tests (localVue with BootstrapVue, in-memory router)
  styles/         _variables.scss (design tokens + Bootstrap overrides), main.scss
```

## Routes

| Path | Screen |
|---|---|
| `/` | Listing with search (`?search=`, name or CPF — full or 3+ digits, masked or not), filters (`?sex=M\|F&age_min=&age_max=`, 0–150), pagination (`?page=`, `?per_page=` 10/25/50), sorting (`?sort=name\|birth_date&direction=asc\|desc`), "Exportar CSV", detail/edit/delete actions |
| `/pacientes/lixeira` | Trash ("Pacientes excluídos"): search, pagination, "Restaurar", "Exportar CSV" (`trashed=only`) |
| `/pacientes/novo` | Create |
| `/pacientes/:id` | Detail with tabs "Dados" / "Histórico" / "Atendimentos" / "Linha do tempo" (`?aba=historico\|atendimentos\|linha-do-tempo`) |
| `/pacientes/:id/atendimentos/novo` | New consultation (Data, Hora, Duração, Profissional, CRM, Queixa principal, Anotações) |
| `/atendimentos/:id` | Consultation: summary, status actions, edit (notes only in final states), prontuário (OD/OE) and "Baixar receita (PDF)" when completed |
| `/agenda` | Weekly agenda Mon–Sun (`?semana=YYYY-MM-DD`, Monday; `?status=a,b`; `?profissional=`), quick status actions |
| `/pacientes/:id/editar` | Edit |

Export downloads `GET /patients/export` through axios as a Blob (errors become a toast). The filename comes from
`Content-Disposition` when the API exposes it to the browser (`Access-Control-Expose-Headers`), otherwise it is
built locally as `pacientes-AAAAMMDD-HHMM.csv`. The history tab is loaded the first time it opens and pages with
"Carregar mais"; the request IP / user agent are not shown (without login they identify no one — data minimization).

Consultation dates follow America/Sao_Paulo (fixed UTC−03:00, no DST since 2019): API datetimes are read as
São Paulo wall clock, `scheduled_at` is sent as `YYYY-MM-DDTHH:mm` (the API reads it in São Paulo) and week ranges
are computed on `Date.UTC` dates, so nothing depends on the browser's zone. Diopters are shown as `+1.25` / `−0.50`
in 0.25 steps (arrow keys or −/+); the axis is only enabled when the cylinder is not 0.

Notes: dates are converted by string splitting (never `new Date('YYYY-MM-DD')`, which shifts a day in UTC-3);
CPF/RG/phone are sent unmasked; server 422 `errors` are mapped onto the form fields.
The CPF field checks availability on blur (`GET /patients/cpf-availability`, debounced, stale requests aborted);
any failure is ignored, so it never blocks saving. The delete toast offers "Desfazer" (restore) and stays 10 s,
pausing on hover/focus. Age uses the API's `age`, falling back to a calculation from `birth_date`.

## Accessibility

Checked with axe-core (WCAG 2.2 A/AA + best practices) on every screen at 1280px and 375px: 0 violations.
Route changes move focus to the page `<h1>` and announce the new title (only on path changes, so
typing in the search box keeps focus); field errors are linked with `aria-describedby`/`aria-invalid`;
success toasts are `role="status"`, error toasts `role="alert"`; the delete modal traps focus and returns it.

## Production image

Multi-stage `Dockerfile` (node:20-alpine build → nginx:1.27-alpine) with `docker/nginx.conf`:
SPA history fallback, gzip, 1-year immutable cache for hashed `/assets/*`, `no-cache` for `index.html`,
security headers, `/healthz`. `VITE_API_URL` is baked in at build time and is required.

```sh
docker build --build-arg VITE_API_URL=http://localhost:8001/api -t patients-web:prod .
docker run --rm -p 8091:80 patients-web:prod   # http://localhost:8091
```

The API must allow the page's origin in CORS (e.g. `http://localhost:8091`). The dev `web` compose
service does not use this Dockerfile.
