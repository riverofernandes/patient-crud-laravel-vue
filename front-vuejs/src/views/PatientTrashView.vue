<template>
  <section>
    <AppBreadcrumb :items="breadcrumb" />

    <header class="page-header">
      <div>
        <h1 class="page-title" tabindex="-1">Pacientes excluídos</h1>
        <p class="page-subtitle">
          Pacientes excluídos podem ser restaurados e voltam para a listagem.
        </p>
      </div>
    </header>

    <div class="app-card">
      <div class="list-toolbar">
        <div class="search-field" role="search">
          <label for="trash-search" class="sr-only"
            >Buscar por nome do paciente ou CPF</label
          >
          <b-icon-search class="search-field__icon" aria-hidden="true" />
          <b-form-input
            id="trash-search"
            v-model="search"
            type="search"
            placeholder="Buscar por nome do paciente ou CPF"
            autocomplete="off"
            aria-describedby="trash-search-help"
          />
          <p id="trash-search-help" class="search-field__help">
            CPF: informe os 11 dígitos
          </p>
        </div>
        <ExportCsvButton
          class="list-toolbar__export"
          :params="exportParams"
          :disabled="isEmptyResult"
        />
      </div>

      <div v-if="error" class="state-box" role="alert">
        <p class="state-box__title">
          Não foi possível carregar os pacientes excluídos
        </p>
        <p class="mb-3">{{ error }}</p>
        <b-button variant="primary" @click="fetch">Tentar novamente</b-button>
      </div>

      <template v-else>
        <b-table
          ref="table"
          class="patients-table trash-table"
          aria-label="Lista de pacientes excluídos"
          :items="patients"
          :fields="fields"
          :busy="loading"
          primary-key="id"
          responsive
          show-empty
        >
          <template #table-busy>
            <div class="state-box" role="status">
              <b-spinner variant="primary" class="mb-2" />
              <p class="mb-0">Carregando pacientes excluídos...</p>
            </div>
          </template>

          <template #empty>
            <div class="state-box">
              <p class="state-box__title">Nenhum paciente excluído</p>
              <p class="mb-0">
                {{
                  activeSearch
                    ? "Tente buscar por outro nome ou CPF."
                    : "Os pacientes que você excluir aparecem aqui."
                }}
              </p>
            </div>
          </template>

          <template #head(actions)="{ label }">
            <span class="sr-only">{{ label }}</span>
          </template>

          <template #cell(cpf)="{ value }">{{ maskCpf(value) }}</template>
          <template #cell(deleted_at)="{ value }">{{
            formatDateTime(value)
          }}</template>

          <template #cell(actions)="{ item }">
            <b-button
              variant="outline-primary"
              size="sm"
              class="restore-btn"
              :disabled="restoringId !== null && restoringId !== item.id"
              :aria-label="`Restaurar ${item.name}`"
              @click="restore(item)"
            >
              <b-spinner
                v-if="restoringId === item.id"
                small
                class="mr-1"
                label="Restaurando"
              />
              <b-icon-arrow-counterclockwise
                v-else
                aria-hidden="true"
                class="mr-1"
              />
              Restaurar
            </b-button>
          </template>
        </b-table>

        <div v-if="meta && meta.total > 0" class="list-footer">
          <span class="list-footer__info" aria-live="polite">
            Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
          </span>
          <b-pagination
            :value="meta.current_page"
            :total-rows="meta.total"
            :per-page="meta.per_page"
            :limit="5"
            hide-goto-end-buttons
            class="app-pagination"
            aria-label="Paginação"
            label-prev-page="Página anterior"
            label-next-page="Próxima página"
            label-page="Ir para a página"
            @change="goToPage"
          >
            <template #prev-text
              ><b-icon-chevron-left aria-hidden="true"
            /></template>
            <template #next-text
              ><b-icon-chevron-right aria-hidden="true"
            /></template>
          </b-pagination>
        </div>
      </template>
    </div>
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import AppBreadcrumb from "@/components/AppBreadcrumb.vue";
import ExportCsvButton from "@/components/ExportCsvButton.vue";
import {
  patientService,
  type ExportParams,
  type PaginationMeta,
  type Patient,
} from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { formatDateTime, maskCpf } from "@/utils/format";
import { debounce } from "@/utils/debounce";
import { PATIENT_RESTORED_EVENT, restorePatient } from "@/utils/restore";
import { focusPageHeading } from "@/utils/routeFocus";

const PER_PAGE = 10;

export default defineComponent({
  name: "PatientTrashView",
  components: { AppBreadcrumb, ExportCsvButton },
  data() {
    return {
      search: "",
      patients: [] as Patient[],
      meta: null as PaginationMeta | null,
      loading: false,
      error: "",
      requestId: 0,
      restoringId: null as number | null,
      breadcrumb: [
        { text: "Pacientes", to: { name: "patients.index" } },
        { text: "Pacientes excluídos" },
      ],
      fields: [
        { key: "name", label: "Nome do paciente", thClass: "col-name" },
        { key: "cpf", label: "CPF", thClass: "col-cpf" },
        {
          key: "deleted_at",
          label: "Data de exclusão",
          thClass: "col-deleted",
        },
        {
          key: "actions",
          label: "Ações",
          thClass: "col-actions",
          tdClass: "col-actions",
        },
      ],
    };
  },
  computed: {
    activeSearch(): string {
      const q = this.$route.query.search;
      return typeof q === "string" ? q : "";
    },
    currentPage(): number {
      const page = Number(this.$route.query.page);
      return Number.isInteger(page) && page > 0 ? page : 1;
    },
    /** Exports the trash with the current search. */
    exportParams(): ExportParams {
      return { search: this.activeSearch || undefined, trashed: "only" };
    },
    isEmptyResult(): boolean {
      return !this.loading && !!this.meta && this.meta.total === 0;
    },
  },
  watch: {
    "$route.query": {
      immediate: true,
      handler() {
        if (this.search.trim() !== this.activeSearch)
          this.search = this.activeSearch;
        this.fetch();
      },
    },
    search(value: string) {
      this.applySearch(value);
    },
  },
  created() {
    this.applySearch = debounce((value: string) => {
      const term = value.trim();
      if (term === this.activeSearch) return;
      this.updateQuery({ search: term, page: 1 });
    }, 400);
    // A "Desfazer" toast can restore a patient while this page is open.
    this.$root.$on(PATIENT_RESTORED_EVENT, this.fetch);
  },
  beforeDestroy() {
    this.applySearch.cancel?.();
    this.$root.$off(PATIENT_RESTORED_EVENT, this.fetch);
  },
  methods: {
    maskCpf,
    formatDateTime,
    // replaced with a debounced version in created()
    applySearch: (() => undefined) as ((value: string) => void) & {
      cancel?: () => void;
    },
    updateQuery(
      { search, page }: { search?: string; page?: number },
      mode: "push" | "replace" = "replace"
    ): void {
      const term = search ?? this.activeSearch;
      const nextPage = page ?? this.currentPage;
      const query: Record<string, string> = {};
      if (term) query.search = term;
      if (nextPage > 1) query.page = String(nextPage);
      this.$router[mode]({ query }).catch(() => undefined);
    },
    goToPage(page: number): void {
      this.updateQuery({ page }, "push");
    },
    async fetch(): Promise<void> {
      const requestId = ++this.requestId;
      this.loading = true;
      this.error = "";
      try {
        const result = await patientService.list({
          search: this.activeSearch || undefined,
          page: this.currentPage,
          per_page: PER_PAGE,
          trashed: "only",
        });
        if (requestId !== this.requestId) return;
        if (
          result.data.length === 0 &&
          this.currentPage > 1 &&
          result.meta.last_page > 0
        ) {
          this.updateQuery({
            page: Math.min(this.currentPage - 1, result.meta.last_page),
          });
          return;
        }
        this.patients = result.data;
        this.meta = result.meta;
      } catch (e) {
        if (requestId !== this.requestId) return;
        this.error = getErrorMessage(e, "Tente novamente em instantes.");
      } finally {
        if (requestId === this.requestId) this.loading = false;
      }
    },
    async restore(patient: Patient): Promise<void> {
      if (this.restoringId !== null) return;
      this.restoringId = patient.id;
      const result = await restorePatient(this.$root, patient);
      this.restoringId = null;
      if (result === "failed") return;
      // Restored (or no longer in the trash): drop the row locally.
      const index = this.patients.findIndex((p) => p.id === patient.id);
      if (index >= 0) this.patients.splice(index, 1);
      if (this.meta) {
        this.meta = {
          ...this.meta,
          total: Math.max(this.meta.total - 1, 0),
          to: this.meta.to !== null ? this.meta.to - 1 : null,
        };
      }
      if (this.patients.length === 0) {
        this.fetch();
        focusPageHeading(document.getElementById("main-content"));
        return;
      }
      // The clicked button is gone: move focus to the next row's button (or the
      // previous one at the end), never letting it fall back to <body>.
      this.$nextTick(() => {
        const buttons =
          this.$el.querySelectorAll<HTMLButtonElement>(".restore-btn");
        const target = buttons[Math.min(index, buttons.length - 1)];
        if (target) target.focus();
        else focusPageHeading(document.getElementById("main-content"));
      });
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/patient-table";

.list-toolbar__export {
  flex: 0 0 auto;
  margin-bottom: 16px;
}

.page-subtitle {
  margin: 4px 0 0;
  color: $text-muted;
  font-size: 14px;
}

.trash-table ::v-deep .col-name {
  width: auto;
}

.trash-table ::v-deep .col-cpf,
.trash-table ::v-deep .col-deleted {
  width: 1%;
}

.restore-btn {
  display: inline-flex;
  align-items: center;
  font-size: 14px;
  line-height: 20px;
}
</style>
