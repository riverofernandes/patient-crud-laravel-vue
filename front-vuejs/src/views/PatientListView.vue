<template>
  <section>
    <header class="page-header">
      <h1 class="page-title">Pacientes</h1>
      <b-button variant="primary" :to="{ name: 'patients.create' }"
        >Novo paciente</b-button
      >
    </header>

    <div class="app-card">
      <div class="search-field" role="search">
        <label for="patient-search" class="sr-only"
          >Buscar por nome do paciente ou CPF</label
        >
        <b-icon-search class="search-field__icon" aria-hidden="true" />
        <b-form-input
          id="patient-search"
          v-model="search"
          type="search"
          placeholder="Buscar por nome do paciente ou CPF"
          autocomplete="off"
        />
      </div>

      <div v-if="error" class="state-box" role="alert">
        <p class="state-box__title">Não foi possível carregar os pacientes</p>
        <p class="mb-3">{{ error }}</p>
        <b-button variant="primary" @click="fetch">Tentar novamente</b-button>
      </div>

      <template v-else>
        <b-table
          class="patients-table"
          :items="patients"
          :fields="fields"
          :busy="loading"
          primary-key="id"
          responsive
          show-empty
          hover
          tbody-tr-class="patients-table__row"
          @row-clicked="openDetail"
        >
          <template #table-busy>
            <div class="state-box" role="status">
              <b-spinner variant="primary" class="mb-2" />
              <p class="mb-0">Carregando pacientes...</p>
            </div>
          </template>

          <template #empty>
            <div class="state-box">
              <p class="state-box__title">Nenhum paciente encontrado</p>
              <p class="mb-0">
                {{
                  activeSearch
                    ? "Tente buscar por outro nome ou CPF."
                    : 'Clique em "Novo paciente" para cadastrar o primeiro.'
                }}
              </p>
            </div>
          </template>

          <template #head(actions)="{ label }">
            <span class="sr-only">{{ label }}</span>
          </template>

          <template #cell(cpf)="{ value }">{{ maskCpf(value) }}</template>
          <template #cell(phone)="{ value }">{{ maskPhone(value) }}</template>
          <template #cell(birth_date)="{ value }">{{
            isoToBr(value)
          }}</template>

          <template #cell(actions)="{ item }">
            <div class="row-actions">
              <b-button
                v-b-tooltip.hover.top="'Ver detalhes'"
                variant="link"
                class="row-actions__btn"
                :to="{ name: 'patients.show', params: { id: String(item.id) } }"
                :aria-label="`Ver detalhes de ${item.name}`"
                @click.native.stop
              >
                <b-icon-eye-fill aria-hidden="true" />
              </b-button>
              <b-button
                v-b-tooltip.hover.top="'Editar'"
                variant="link"
                class="row-actions__btn"
                :to="{ name: 'patients.edit', params: { id: String(item.id) } }"
                :aria-label="`Editar ${item.name}`"
                @click.native.stop
              >
                <b-icon-pencil-fill aria-hidden="true" />
              </b-button>
              <b-button
                v-b-tooltip.hover.top="'Excluir'"
                variant="link"
                class="row-actions__btn row-actions__btn--danger"
                :aria-label="`Excluir ${item.name}`"
                @click.stop="patientToDelete = item"
              >
                <b-icon-trash-fill aria-hidden="true" />
              </b-button>
            </div>
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

    <ConfirmDeleteModal :patient.sync="patientToDelete" @deleted="onDeleted" />
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import ConfirmDeleteModal from "@/components/ConfirmDeleteModal.vue";
import {
  patientService,
  type PaginationMeta,
  type Patient,
} from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { isoToBr, maskCpf, maskPhone } from "@/utils/format";
import { debounce } from "@/utils/debounce";

const PER_PAGE = 10;

export default defineComponent({
  name: "PatientListView",
  components: { ConfirmDeleteModal },
  data() {
    return {
      search: "",
      patients: [] as Patient[],
      meta: null as PaginationMeta | null,
      loading: false,
      error: "",
      requestId: 0,
      patientToDelete: null as Patient | null,
      fields: [
        { key: "name", label: "Nome do paciente", thClass: "col-name" },
        { key: "cpf", label: "CPF", thClass: "col-cpf" },
        { key: "phone", label: "Celular", thClass: "col-phone" },
        {
          key: "birth_date",
          label: "Data de nascimento",
          thClass: "col-birth",
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
  },
  beforeDestroy() {
    this.applySearch.cancel?.();
  },
  methods: {
    maskCpf,
    maskPhone,
    isoToBr,
    // replaced with a debounced version in created()
    applySearch: (() => undefined) as ((value: string) => void) & {
      cancel?: () => void;
    },
    /**
     * Writes search/page to the query string (the source of truth, watched above).
     * Page changes use `push` so Back/Forward walk through pages; debounced typing
     * and automatic corrections use `replace` to avoid flooding the history.
     */
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
        });
        if (requestId !== this.requestId) return;
        // Page out of range (e.g. after deleting the last item of a page).
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
    openDetail(item: Patient): void {
      this.$router.push({
        name: "patients.show",
        params: { id: String(item.id) },
      });
    },
    onDeleted(): void {
      this.fetch();
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.search-field {
  position: relative;
  margin-bottom: 16px;

  ::v-deep .form-control {
    padding-left: 47px;

    &::-webkit-search-cancel-button {
      cursor: pointer;
    }
  }
}

.search-field__icon {
  position: absolute;
  top: 10px;
  left: 17px;
  color: $text-muted;
  font-size: 20px;
  pointer-events: none;
}

.patients-table {
  position: relative; // contains the absolutely positioned .sr-only header text
  margin-bottom: 0;
  border: 1px solid $border;
  border-radius: 8px;

  ::v-deep table {
    margin-bottom: 0;
    color: $text;
    font-size: 16px;
  }

  ::v-deep thead th {
    height: 55px;
    padding: 0 24px;
    color: $navy;
    font-weight: 700;
    white-space: nowrap;
    vertical-align: middle;
    background: $page-bg;
    border-top: 0;
    border-bottom: 1px solid $border;
  }

  ::v-deep tbody td {
    height: 56px;
    padding: 0 24px;
    white-space: nowrap;
    vertical-align: middle;
    border-top: 1px solid $border;
  }

  ::v-deep tbody tr:first-child td {
    border-top: 0;
  }

  ::v-deep .patients-table__row {
    cursor: pointer;
  }

  ::v-deep .table-hover tbody tr:hover {
    color: $text;
    background: rgba($blue, 0.03);
  }

  ::v-deep .b-table-empty-row td,
  ::v-deep .b-table-busy-slot td {
    cursor: default;
    white-space: normal;
  }

  ::v-deep .col-name {
    width: 41%;
  }

  ::v-deep .col-cpf,
  ::v-deep .col-phone {
    width: 16%;
  }

  ::v-deep .col-actions {
    width: 1%;
    padding-right: 14px;
    padding-left: 0;
    text-align: right;
  }
}

.row-actions {
  display: inline-flex;
  gap: 2px;
}

.row-actions__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  color: $blue;
  font-size: 18px;
  border-radius: 6px;

  &:hover,
  &:focus {
    color: $blue-dark;
    background: rgba($blue, 0.08);
  }

  &--danger {
    color: $red;

    &:hover,
    &:focus {
      color: darken($red, 10%);
      background: rgba($red, 0.08);
    }
  }
}

.list-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-top: 16px;
}

.list-footer__info {
  color: $text-muted;
  font-size: 14px;
}

.app-pagination {
  gap: 10px;
  margin: 0 0 0 auto;

  ::v-deep .page-item {
    .page-link {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 30px;
      height: 30px;
      margin: 0;
      padding: 0 8px;
      color: $text-muted;
      font-size: 14px;
      font-weight: 700;
      border: 1px solid $border-light;
      border-radius: 4px !important;
    }

    &:first-child .page-link,
    &:last-child .page-link {
      color: $blue;
    }

    &.active .page-link {
      color: #fff;
      background: $blue;
      border-color: $blue;
    }

    &.disabled .page-link {
      color: $text-muted;
      background: $page-bg;
      border-color: $border-light;
    }
  }
}
</style>
