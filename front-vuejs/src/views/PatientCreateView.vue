<template>
  <section>
    <AppBreadcrumb :items="breadcrumb" />
    <PatientForm
      title="Cadastro de novo paciente"
      submit-label="Salvar paciente"
      :busy="saving"
      :server-errors="serverErrors"
      @submit="save"
    />
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import AppBreadcrumb from "@/components/AppBreadcrumb.vue";
import PatientForm from "@/components/PatientForm.vue";
import { patientService, type PatientPayload } from "@/services/patientService";
import {
  getErrorMessage,
  getValidationErrors,
  type ValidationErrors,
} from "@/services/api";
import { notify } from "@/utils/notify";

export default defineComponent({
  name: "PatientCreateView",
  components: { AppBreadcrumb, PatientForm },
  data() {
    return {
      saving: false,
      serverErrors: null as ValidationErrors | null,
      breadcrumb: [
        { text: "Pacientes", to: { name: "patients.index" } },
        { text: "Novo paciente" },
      ],
    };
  },
  methods: {
    async save(payload: PatientPayload): Promise<void> {
      this.saving = true;
      this.serverErrors = null;
      try {
        await patientService.create(payload);
        notify(this.$root, "success", "Paciente cadastrado com sucesso!");
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
            getErrorMessage(error, "Não foi possível salvar o paciente.")
          );
        }
      } finally {
        this.saving = false;
      }
    },
  },
});
</script>
