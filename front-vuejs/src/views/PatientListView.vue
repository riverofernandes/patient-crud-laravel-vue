<template>
  <section>
    <header class="page-header">
      <h1 class="page-title" tabindex="-1">Pacientes</h1>
      <div class="page-header__actions">
        <b-button variant="outline-primary" :to="{ name: 'patients.trash' }">
          <b-icon-trash aria-hidden="true" class="mr-1" />
          Lixeira
        </b-button>
        <b-button variant="primary" :to="{ name: 'patients.create' }"
          >Novo paciente</b-button
        >
      </div>
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
          aria-label="Lista de pacientes"
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

          <!-- Sortable headers: a real <button> (keyboard + screen readers), the
               state is exposed with aria-sort on the <th> (see `fields`). The icon
               is aria-hidden so the column name stays just the label. -->
          <template #head(name)="{ label }">
            <button type="button" class="sort-btn" @click="toggleSort('name')">
              {{ label }}
              <component
                :is="sortIcon('name')"
                class="sort-btn__icon"
                :class="{ 'is-active': sort === 'name' }"
                aria-hidden="true"
              />
            </button>
          </template>
          <template #head(birth_date)="{ label }">
            <button
              type="button"
              class="sort-btn"
              @click="toggleSort('birth_date')"
            >
              {{ label }}
              <component
                :is="sortIcon('birth_date')"
                class="sort-btn__icon"
                :class="{ 'is-active': sort === 'birth_date' }"
                aria-hidden="true"
              />
            </button>
          </template>

          <template #head(actions)="{ label }">
            <span class="sr-only">{{ label }}</span>
          </template>

          <template #cell(cpf)="{ value }">{{ maskCpf(value) }}</template>
          <template #cell(phone)="{ value }">{{ maskPhone(value) }}</template>
          <template #cell(birth_date)="{ value }">{{
            isoToBr(value)
          }}</template>
          <template #cell(age)="{ item }">{{ patientAge(item) }}</template>

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
          <div class="list-footer__start">
            <span class="list-footer__info" aria-live="polite">
              Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
            </span>
            <div class="per-page">
              <label for="per-page">Itens por página</label>
              <b-form-select
                id="per-page"
                :value="perPage"
                :options="perPageOptions"
                @change="setPerPage"
              />
            </div>
          </div>
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
  type SortDirection,
} from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { isoToBr, maskCpf, maskPhone, patientAge } from "@/utils/format";
import { debounce } from "@/utils/debounce";
import { PATIENT_RESTORED_EVENT } from "@/utils/restore";

export const PER_PAGE_OPTIONS = [10, 25, 50] as const;
const DEFAULT_PER_PAGE = 10;
const SORTABLE = ["name", "birth_date"] as const;
type SortKey = (typeof SORTABLE)[number];
const DEFAULT_SORT: SortKey = "name";
const DEFAULT_DIRECTION: SortDirection = "asc";

interface ListQuery {
  search: string;
  page: number;
  perPage: number;
  sort: SortKey;
  direction: SortDirection;
}

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
      perPageOptions: [...PER_PAGE_OPTIONS],
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
    perPage(): number {
      const value = Number(this.$route.query.per_page);
      return (PER_PAGE_OPTIONS as readonly number[]).includes(value)
        ? value
        : DEFAULT_PER_PAGE;
    },
    sort(): SortKey {
      const value = this.$route.query.sort;
      return (SORTABLE as readonly string[]).includes(value as string)
        ? (value as SortKey)
        : DEFAULT_SORT;
    },
    direction(): SortDirection {
      return this.$route.query.direction === "desc" ? "desc" : "asc";
    },
    fields(): Array<Record<string, unknown>> {
      const ariaSort = (key: SortKey) => ({
        "aria-sort":
          this.sort === key
            ? this.direction === "asc"
              ? "ascending"
              : "descending"
            : "none",
      });
      return [
        {
          key: "name",
          label: "Nome do paciente",
          thClass: "col-name",
          thAttr: ariaSort("name"),
        },
        { key: "cpf", label: "CPF", thClass: "col-cpf" },
        { key: "phone", label: "Celular", thClass: "col-phone" },
        {
          key: "birth_date",
          label: "Data de nascimento",
          thClass: "col-birth",
          thAttr: ariaSort("birth_date"),
        },
        { key: "age", label: "Idade", thClass: "col-age" },
        {
          key: "actions",
          label: "Ações",
          thClass: "col-actions",
          tdClass: "col-actions",
        },
      ];
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
    // "Desfazer" on the delete toast brings a patient back: show it again.
    this.$root.$on(PATIENT_RESTORED_EVENT, this.fetch);
  },
  beforeDestroy() {
    this.applySearch.cancel?.();
    this.$root.$off(PATIENT_RESTORED_EVENT, this.fetch);
  },
  methods: {
    maskCpf,
    maskPhone,
    isoToBr,
    patientAge,
    // replaced with a debounced version in created()
    applySearch: (() => undefined) as ((value: string) => void) & {
      cancel?: () => void;
    },
    /**
     * Writes the list state to the query string (the source of truth, watched above),
     * merging with the current values and omitting defaults so plain URLs stay plain.
     * Page/sort/per-page changes use `push` so Back/Forward walk through them;
     * debounced typing and automatic corrections use `replace`.
     */
    updateQuery(
      changes: Partial<ListQuery>,
      mode: "push" | "replace" = "replace"
    ): void {
      const next: ListQuery = {
        search: this.activeSearch,
        page: this.currentPage,
        perPage: this.perPage,
        sort: this.sort,
        direction: this.direction,
        ...changes,
      };
      const query: Record<string, string> = {};
      if (next.search) query.search = next.search;
      if (next.page > 1) query.page = String(next.page);
      if (next.perPage !== DEFAULT_PER_PAGE)
        query.per_page = String(next.perPage);
      if (next.sort !== DEFAULT_SORT || next.direction !== DEFAULT_DIRECTION) {
        query.sort = next.sort;
        query.direction = next.direction;
      }
      this.$router[mode]({ query }).catch(() => undefined);
    },
    goToPage(page: number): void {
      this.updateQuery({ page }, "push");
    },
    toggleSort(key: SortKey): void {
      const direction: SortDirection =
        this.sort === key && this.direction === "asc" ? "desc" : "asc";
      this.updateQuery({ sort: key, direction, page: 1 }, "push");
    },
    sortIcon(key: SortKey): string {
      if (this.sort !== key) return "b-icon-arrow-down-up";
      return this.direction === "asc"
        ? "b-icon-caret-up-fill"
        : "b-icon-caret-down-fill";
    },
    setPerPage(value: number): void {
      this.updateQuery({ perPage: Number(value), page: 1 }, "push");
    },
    async fetch(): Promise<void> {
      const requestId = ++this.requestId;
      this.loading = true;
      this.error = "";
      try {
        const result = await patientService.list({
          search: this.activeSearch || undefined,
          page: this.currentPage,
          per_page: this.perPage,
          sort: this.sort,
          direction: this.direction,
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
@import "@/styles/patient-table";

.page-header__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.patients-table ::v-deep .col-age {
  width: 1%;
}
</style>
