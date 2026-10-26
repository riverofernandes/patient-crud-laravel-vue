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
| `npm run test` | Vitest unit tests for `src/utils` |

## Structure

```
src/
  services/       api.ts (axios instance + error helpers), patientService.ts (typed CRUD)
  composables/    usePatient.ts (load one patient: loading / 404 / error)
  components/     AppLayout, AppBreadcrumb, PatientForm, FormField, DetailItem,
                  ConfirmDeleteModal, PageState
  views/          PatientListView, PatientCreateView, PatientDetailView, PatientEditView, NotFoundView
  utils/          format.ts (CPF/RG/phone/date masks, DD/MM/AAAA <-> YYYY-MM-DD),
                  validation.ts (CPF check digits, e-mail), notify.ts (toasts), debounce.ts
  styles/         _variables.scss (design tokens + Bootstrap overrides), main.scss
```

## Routes

| Path | Screen |
|---|---|
| `/` | Listing with search (`?search=`), pagination (`?page=`), detail/edit/delete actions |
| `/pacientes/novo` | Create |
| `/pacientes/:id` | Detail |
| `/pacientes/:id/editar` | Edit |

Notes: dates are converted by string splitting (never `new Date('YYYY-MM-DD')`, which shifts a day in UTC-3);
CPF/RG/phone are sent unmasked; server 422 `errors` are mapped onto the form fields.
