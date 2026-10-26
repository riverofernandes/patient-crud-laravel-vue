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
      <h1 class="card-title-lg">{{ patient.name }}</h1>

      <div class="patient-detail__body">
        <dl class="patient-grid patient-grid--row1">
          <DetailItem label="Nome completo" :value="patient.name" />
          <DetailItem label="CPF" :value="maskCpf(patient.cpf)" />
          <DetailItem label="RG" :value="maskRg(patient.rg)" />
        </dl>
        <dl class="patient-grid patient-grid--row2">
          <DetailItem
            label="Data de nascimento"
            :value="isoToBr(patient.birth_date)"
          />
          <DetailItem label="Sexo biológico" :value="formatSex(patient.sex)" />
          <DetailItem label="Celular" :value="maskPhone(patient.phone)" />
          <DetailItem label="E-mail" :value="patient.email" />
        </dl>
        <dl>
          <DetailItem
            label="Descrição"
            :value="patient.description"
            multiline
          />
        </dl>
      </div>

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
import { usePatient } from "@/composables/usePatient";
import { focusHeadingIfParked } from "@/utils/routeFocus";
import type { Patient } from "@/services/patientService";
import { formatSex, isoToBr, maskCpf, maskPhone, maskRg } from "@/utils/format";

export default defineComponent({
  name: "PatientDetailView",
  components: { AppBreadcrumb, ConfirmDeleteModal, DetailItem, PageState },
  props: {
    id: { type: String, required: true },
  },
  setup(props) {
    return usePatient(toRef(props, "id"));
  },
  data() {
    return { patientToDelete: null as Patient | null };
  },
  computed: {
    breadcrumb(): BreadcrumbItem[] {
      return [
        { text: "Pacientes", to: { name: "patients.index" } },
        { text: this.patient?.name ?? "Paciente" },
      ];
    },
  },
  watch: {
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
    onDeleted(): void {
      this.$router.push({ name: "patients.index" });
    },
  },
});
</script>

<style lang="scss" scoped>
.patient-detail {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 190px);
}

.patient-detail__body {
  padding-top: 32px;

  dl {
    margin: 0;
  }
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
</style>
