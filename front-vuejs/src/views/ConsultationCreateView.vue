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

    <div v-else-if="patient" class="app-card consultation-create">
      <ConsultationForm
        title="Novo atendimento"
        submit-label="Agendar atendimento"
        :busy="saving"
        :server-errors="serverErrors"
        @submit="save"
      >
        <template #cancel>
          <router-link :to="backTo" class="btn-link-danger"
            >Cancelar</router-link
          >
        </template>
        <template #intro>
          <p class="consultation-create__patient">
            Paciente: <strong>{{ patient.name }}</strong>
          </p>
        </template>
      </ConsultationForm>
    </div>
  </section>
</template>

<script lang="ts">
import { defineComponent, toRef } from "vue";
import type { RawLocation } from "vue-router";
import AppBreadcrumb, {
  type BreadcrumbItem,
} from "@/components/AppBreadcrumb.vue";
import ConsultationForm from "@/components/ConsultationForm.vue";
import PageState from "@/components/PageState.vue";
import { usePatient } from "@/composables/usePatient";
import {
  consultationService,
  type ConsultationPayload,
} from "@/services/consultationService";
import type { Patient } from "@/services/patientService";
import {
  getErrorMessage,
  getValidationErrors,
  isNotFound,
  type ValidationErrors,
} from "@/services/api";
import { notify } from "@/utils/notify";
import { focusHeadingIfParked } from "@/utils/routeFocus";

export default defineComponent({
  name: "ConsultationCreateView",
  components: { AppBreadcrumb, ConsultationForm, PageState },
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
    };
  },
  computed: {
    backTo(): RawLocation {
      return {
        name: "patients.show",
        params: { id: this.id },
        query: { aba: "atendimentos" },
      };
    },
    breadcrumb(): BreadcrumbItem[] {
      return [
        { text: "Pacientes", to: { name: "patients.index" } },
        {
          text: this.patient?.name ?? "Paciente",
          to: { name: "patients.show", params: { id: this.id } },
        },
        { text: "Novo atendimento" },
      ];
    },
  },
  watch: {
    patient(value: Patient | null) {
      if (!value) return;
      document.title = `Novo atendimento: ${value.name} | EyeCare`;
      this.$nextTick(focusHeadingIfParked);
    },
  },
  methods: {
    async save(payload: ConsultationPayload): Promise<void> {
      this.saving = true;
      this.serverErrors = null;
      try {
        const consultation = await consultationService.create(this.id, payload);
        notify(this.$root, "success", "Atendimento agendado com sucesso!");
        this.$router.push({
          name: "consultations.show",
          params: { id: String(consultation.id) },
        });
      } catch (error) {
        const errors = getValidationErrors(error);
        if (errors) {
          this.serverErrors = errors;
          notify(
            this.$root,
            "error",
            errors.scheduled_at?.[0] ??
              "Verifique os campos destacados para salvar!"
          );
        } else if (isNotFound(error)) {
          notify(this.$root, "error", "Paciente não encontrado.");
        } else {
          notify(
            this.$root,
            "error",
            getErrorMessage(error, "Não foi possível agendar o atendimento.")
          );
        }
      } finally {
        this.saving = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.consultation-create__patient {
  margin: 0;
  padding-top: 16px;
  color: $text;
}
</style>
