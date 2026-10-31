<template>
  <section>
    <AppBreadcrumb :items="breadcrumb" />

    <PageState v-if="loading" title="Carregando paciente..." />

    <PageState
      v-else-if="notFound"
      variant="not-found"
      title="Paciente não encontrado"
      message="O paciente que você procura não existe ou foi excluído."
    >
      <b-button variant="primary" :to="{ name: 'patients.index' }"
        >Voltar para pacientes</b-button
      >
    </PageState>

    <PageState
      v-else-if="error"
      variant="error"
      title="Algo deu errado"
      :message="error"
    >
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </PageState>

    <article v-else-if="patient" class="app-card patient-detail">
      <header class="patient-detail__header">
        <PatientAvatar
          size="lg"
          :name="patient.name"
          :photo-url="patient.photo_url"
        />
        <div class="patient-detail__heading">
          <h1 class="card-title-lg patient-detail__title">
            {{ patient.name }}
          </h1>
          <PatientPhoto :patient="patient" @updated="onPhotoUpdated" />
        </div>
      </header>

      <b-tabs
        v-model="tabIndex"
        class="patient-tabs"
        nav-class="patient-tabs__nav"
      >
        <b-tab title="Dados" title-link-class="patient-tabs__link">
          <div class="patient-detail__body">
            <dl class="patient-grid patient-grid--row1">
              <DetailItem label="Nome completo" :value="patient.name" />
              <DetailItem label="CPF" :value="maskCpf(patient.cpf)" />
              <DetailItem label="RG" :value="maskRg(patient.rg)" />
            </dl>
            <dl class="patient-grid patient-grid--with-age">
              <DetailItem
                label="Data de nascimento"
                :value="isoToBr(patient.birth_date)"
              />
              <DetailItem label="Idade" :value="patientAge(patient)" />
              <DetailItem
                label="Sexo biológico"
                :value="formatSex(patient.sex)"
              />
              <DetailItem label="Celular" :value="maskPhone(patient.phone)" />
              <DetailItem label="E-mail" :value="patient.email" />
            </dl>
            <dl>
              <DetailItem
                label="Descrição"
                :value="patient.description"
                multiline
              />
              <DetailItem label="Endereço" :value="addressText" />
            </dl>
          </div>
        </b-tab>
        <b-tab title="Histórico" title-link-class="patient-tabs__link">
          <!-- Mounted the first time the tab opens, then kept (no reloads). -->
          <PatientHistory
            v-if="opened.historico"
            :patient-id="id"
            :refresh-key="refreshKey"
          />
        </b-tab>
        <b-tab title="Atendimentos" title-link-class="patient-tabs__link">
          <PatientConsultations v-if="opened.atendimentos" :patient-id="id" />
        </b-tab>
        <b-tab title="Linha do tempo" title-link-class="patient-tabs__link">
          <PatientTimeline
            v-if="opened['linha-do-tempo']"
            :patient-id="id"
            :refresh-key="refreshKey"
          />
        </b-tab>
      </b-tabs>

      <footer class="form-footer">
        <button
          type="button"
          class="btn-link-danger"
          @click="patientToDelete = patient"
        >
          Excluir paciente
        </button>
        <b-button
          variant="primary"
          :to="{ name: 'patients.edit', params: { id } }"
        >
          Editar paciente
        </b-button>
      </footer>
    </article>

    <ConfirmDeleteModal :patient.sync="patientToDelete" @deleted="onDeleted" />
  </section>
</template>

<script lang="ts">
import { defineComponent, toRef } from "vue";
import AppBreadcrumb, {
  type BreadcrumbItem,
} from "@/components/AppBreadcrumb.vue";
import ConfirmDeleteModal from "@/components/ConfirmDeleteModal.vue";
import DetailItem from "@/components/DetailItem.vue";
import PageState from "@/components/PageState.vue";
import PatientAvatar from "@/components/PatientAvatar.vue";
import PatientConsultations from "@/components/PatientConsultations.vue";
import PatientPhoto from "@/components/PatientPhoto.vue";
import PatientHistory from "@/components/PatientHistory.vue";
import PatientTimeline from "@/components/PatientTimeline.vue";
import { usePatient } from "@/composables/usePatient";
import { focusHeadingIfParked } from "@/utils/routeFocus";
import type { Patient } from "@/services/patientService";
import { formatAddress } from "@/utils/address";
import {
  formatSex,
  isoToBr,
  maskCpf,
  maskPhone,
  maskRg,
  patientAge,
} from "@/utils/format";

/** `?aba=` slug of each tab, in order ("Dados" has none). */
export const TABS = ["", "historico", "atendimentos", "linha-do-tempo"];

export default defineComponent({
  name: "PatientDetailView",
  components: {
    AppBreadcrumb,
    ConfirmDeleteModal,
    DetailItem,
    PageState,
    PatientAvatar,
    PatientConsultations,
    PatientHistory,
    PatientPhoto,
    PatientTimeline,
  },
  props: {
    id: { type: String, required: true },
  },
  setup(props) {
    return usePatient(toRef(props, "id"));
  },
  data() {
    return {
      patientToDelete: null as Patient | null,
      /** Tabs mounted so far (each loads the first time it opens, then stays). */
      opened: {} as Record<string, boolean>,
      /** Bumped after a change made on this page: reloads history/timeline. */
      refreshKey: 0,
    };
  },
  computed: {
    /** Active tab, kept in the query (`?aba=historico`) so it can be linked. */
    tabIndex: {
      get(): number {
        const aba = this.$route.query.aba;
        const index = typeof aba === "string" && aba ? TABS.indexOf(aba) : 0;
        return index > 0 ? index : 0;
      },
      set(index: number): void {
        if (index === this.tabIndex) return;
        const query = { ...this.$route.query };
        if (index > 0 && TABS[index]) query.aba = TABS[index];
        else delete query.aba;
        this.$router.replace({ query }).catch(() => undefined);
      },
    },
    /** One-line address, or "—" when the patient has none. */
    addressText(): string {
      return formatAddress(this.patient?.address) || "—";
    },
    breadcrumb(): BreadcrumbItem[] {
      return [
        { text: "Pacientes", to: { name: "patients.index" } },
        { text: this.patient?.name ?? "Paciente" },
      ];
    },
  },
  watch: {
    tabIndex: {
      immediate: true,
      handler(index: number) {
        const slug = TABS[index];
        if (slug) this.$set(this.opened, slug, true);
      },
    },
    patient(value: Patient | null) {
      if (!value) return;
      document.title = `${value.name} | EyeCare`;
      this.$nextTick(focusHeadingIfParked);
    },
  },
  methods: {
    maskCpf,
    maskRg,
    maskPhone,
    isoToBr,
    formatSex,
    patientAge,
    onPhotoUpdated(updated: Patient): void {
      if (this.patient) this.patient = { ...this.patient, ...updated };
      this.refreshKey++;
    },
    onDeleted(): void {
      this.$router.push({ name: "patients.index" });
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-detail {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 190px);
}

.patient-detail__header {
  display: flex;
  gap: 16px 20px;
  align-items: center;
  padding: 4px 0 24px;
  border-bottom: 2px solid $border-light;
}

.patient-detail__heading {
  min-width: 0;
}

.patient-detail__title {
  margin-bottom: 8px;
  padding: 0;
  overflow-wrap: anywhere;
  border-bottom: 0;
}

.patient-detail__body {
  padding-top: 24px;

  dl {
    margin: 0;
  }
}

// Like .patient-grid--row2 (main.scss) plus a narrow "Idade" column.
.patient-grid--with-age {
  grid-template-columns: 200px 140px 200px 200px minmax(0, 1fr);
}

@media (max-width: 1099.98px) {
  .patient-grid--with-age {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .patient-grid--with-age {
    grid-template-columns: minmax(0, 1fr);
  }
}

// Tabs: underline style in the app's colors.
.patient-tabs {
  margin-top: 16px;
}

.patient-tabs ::v-deep .patient-tabs__nav {
  gap: 8px;
  border-bottom: 1px solid $border;
}

.patient-tabs ::v-deep .patient-tabs__link {
  margin-bottom: -1px;
  padding: 10px 4px;
  color: $text-muted;
  font-weight: 700;
  background: none;
  border: 0;
  border-bottom: 3px solid transparent;
  border-radius: 0;

  &:hover {
    color: $navy;
  }

  &.active {
    color: $blue;
    border-bottom-color: $blue;
  }

  &:focus-visible {
    outline: 2px solid $blue;
    outline-offset: 2px;
  }
}

.patient-tabs ::v-deep .patient-tabs__nav .nav-item + .nav-item {
  margin-left: 16px;
}

.form-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 32px;
}

@media (max-width: 767.98px) {
  .patient-detail {
    min-height: 0;
  }
}

// Four tabs do not fit a phone: one scrollable row instead of wrapping.
@media (max-width: 575.98px) {
  .patient-tabs ::v-deep .patient-tabs__nav {
    flex-wrap: nowrap;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .patient-tabs ::v-deep .patient-tabs__nav .nav-item {
    flex: 0 0 auto;
  }

  .patient-tabs ::v-deep .patient-tabs__link {
    white-space: nowrap;
  }
}
</style>
