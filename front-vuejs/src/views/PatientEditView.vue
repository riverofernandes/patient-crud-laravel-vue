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

    <template v-else-if="patient">
      <PatientForm
        title="Editar paciente"
        submit-label="Atualizar paciente"
        :patient="patient"
        :busy="saving"
        :server-errors="serverErrors"
        @submit="save"
      >
        <template #footer-start>
          <button
            type="button"
            class="btn-link-danger"
            @click="patientToDelete = patient"
          >
            Excluir paciente
          </button>
        </template>
      </PatientForm>

      <ConfirmDeleteModal
        :patient.sync="patientToDelete"
        @deleted="onDeleted"
      />
    </template>
  </section>
</template>

<script lang="ts">
import { defineComponent, toRef } from "vue";
import AppBreadcrumb, {
  type BreadcrumbItem,
} from "@/components/AppBreadcrumb.vue";
import ConfirmDeleteModal from "@/components/ConfirmDeleteModal.vue";
import PageState from "@/components/PageState.vue";
import PatientForm from "@/components/PatientForm.vue";
import { usePatient } from "@/composables/usePatient";
import { focusHeadingIfParked } from "@/utils/routeFocus";
import {
  patientService,
  type Patient,
  type PatientPayload,
} from "@/services/patientService";
import {
  getErrorMessage,
  getValidationErrors,
  type ValidationErrors,
} from "@/services/api";
import { notify } from "@/utils/notify";

export default defineComponent({
  name: "PatientEditView",
  components: { AppBreadcrumb, ConfirmDeleteModal, PageState, PatientForm },
  props: {
    id: { type: String, required: true },
  },
  setup(props) {
    return usePatient(toRef(props, "id"));
  },
  data() {
    return {
      saving: false,
      serverErrors: null as ValidationErrors | null,
      patientToDelete: null as Patient | null,
    };
  },
  watch: {
    patient(value: Patient | null) {
      if (value) this.$nextTick(focusHeadingIfParked);
    },
  },
  computed: {
    breadcrumb(): BreadcrumbItem[] {
      const items: BreadcrumbItem[] = [
        { text: "Pacientes", to: { name: "patients.index" } },
      ];
      if (this.patient) {
        items.push({
          text: this.patient.name,
          to: { name: "patients.show", params: { id: this.id } },
        });
      }
      items.push({ text: "Editar paciente" });
      return items;
    },
  },
  methods: {
    async save(payload: PatientPayload): Promise<void> {
      this.saving = true;
      this.serverErrors = null;
      try {
        await patientService.update(this.id, payload);
        notify(this.$root, "success", "Paciente editado com sucesso!");
        this.$router.push({ name: "patients.index" });
      } catch (error) {
        const errors = getValidationErrors(error);
        if (errors) {
          this.serverErrors = errors;
          notify(
            this.$root,
            "error",
            "Verifique os campos destacados para salvar!"
          );
        } else {
          notify(
            this.$root,
            "error",
            getErrorMessage(error, "Não foi possível atualizar o paciente.")
          );
        }
      } finally {
        this.saving = false;
      }
    },
    onDeleted(): void {
      this.$router.push({ name: "patients.index" });
    },
  },
});
</script>
