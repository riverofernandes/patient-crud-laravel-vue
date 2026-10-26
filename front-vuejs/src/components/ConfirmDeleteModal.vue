<template>
  <b-modal
    :visible="!!patient"
    title="Excluir paciente"
    centered
    no-close-on-backdrop
    :no-close-on-esc="busy"
    :hide-header-close="busy"
    dialog-class="confirm-delete-modal"
    header-close-label="Fechar"
    @hidden="onHidden"
    @hide="onHide"
  >
    <template #modal-header-close>
      <b-icon-x aria-hidden="true" />
    </template>

    <p class="mb-0">
      Atenção! Ao excluir este paciente,
      <strong>essa ação não poderá ser desfeita</strong>. Deseja realmente
      continuar?
    </p>

    <template #modal-footer>
      <button
        type="button"
        class="btn-link-danger"
        :disabled="busy"
        @click="close"
      >
        Cancelar
      </button>
      <b-button variant="primary" :disabled="busy" @click="confirm">
        <b-spinner v-if="busy" small class="mr-1" label="Excluindo" />
        Confirmar
      </b-button>
    </template>
  </b-modal>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import type { BvModalEvent } from "bootstrap-vue";
import { patientService, type Patient } from "@/services/patientService";
import { getErrorMessage, isNotFound } from "@/services/api";
import { notify } from "@/utils/notify";

/**
 * Confirmation dialog that performs the delete itself.
 * Usage: <ConfirmDeleteModal :patient.sync="patientToDelete" @deleted="..." />
 */
export default defineComponent({
  name: "ConfirmDeleteModal",
  props: {
    patient: {
      type: Object as PropType<Pick<Patient, "id" | "name"> | null>,
      default: null,
    },
  },
  data() {
    return { busy: false };
  },
  methods: {
    close(): void {
      this.$emit("update:patient", null);
    },
    onHide(event: BvModalEvent): void {
      if (this.busy) event.preventDefault();
    },
    onHidden(): void {
      if (this.patient) this.close();
    },
    async confirm(): Promise<void> {
      if (!this.patient) return;
      const patient = this.patient;
      this.busy = true;
      try {
        await patientService.remove(patient.id);
        notify(this.$root, "success", "Paciente excluído com sucesso!");
        this.busy = false;
        this.close();
        this.$emit("deleted", patient);
      } catch (error) {
        this.busy = false;
        if (isNotFound(error)) {
          // Already removed elsewhere: treat as success for the user.
          notify(this.$root, "error", "Este paciente já havia sido excluído.");
          this.close();
          this.$emit("deleted", patient);
          return;
        }
        notify(
          this.$root,
          "error",
          getErrorMessage(error, "Não foi possível excluir o paciente.")
        );
      }
    },
  },
});
</script>

<style lang="scss">
@import "@/styles/variables";

.confirm-delete-modal {
  max-width: 448px;

  .modal-header {
    align-items: center;
    padding: 24px 24px 22px;
    border-bottom: 1px solid $border-light;
  }

  .modal-title {
    color: $text;
    font-size: 18px;
    font-weight: 600;
    line-height: 24px;
  }

  .modal-header .close {
    margin: -8px -8px -8px auto;
    padding: 8px;
    color: #6b7cc4;
    font-size: 26px;
    opacity: 1;
    text-shadow: none;
  }

  .modal-body {
    margin: 0 24px;
    padding: 24px 0;
    color: $text;
    font-size: 16px;
    line-height: 24px;
    border-bottom: 1px solid $border;

    strong {
      font-weight: 700;
    }
  }

  .modal-footer {
    justify-content: space-between;
    padding: 24px 24px 24px 32px;
    border-top: 0;

    > * {
      margin: 0;
    }
  }
}
</style>
