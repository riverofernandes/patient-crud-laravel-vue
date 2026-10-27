<template>
  <b-button
    variant="outline-primary"
    class="export-btn"
    :class="{ 'is-busy': loading }"
    :disabled="disabled && !loading"
    :aria-disabled="loading ? 'true' : null"
    :aria-busy="loading ? 'true' : null"
    @click="exportCsv"
  >
    <b-spinner v-if="loading" small class="mr-1" aria-hidden="true" />
    <b-icon-download v-else aria-hidden="true" class="mr-1" />
    {{ loading ? "Exportando..." : "Exportar CSV" }}
  </b-button>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import { patientService, type ExportParams } from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";

/**
 * Downloads GET /patients/export with the given filters as a file. The request
 * goes through axios (blob) rather than a plain link so errors can be shown as
 * a toast and the button can show progress. The button is never `disabled`
 * while busy (that would drop keyboard focus); it uses aria-disabled instead.
 */
export default defineComponent({
  name: "ExportCsvButton",
  props: {
    params: { type: Object as PropType<ExportParams>, default: () => ({}) },
    /** Nothing to export (empty result). */
    disabled: { type: Boolean, default: false },
  },
  data() {
    return { loading: false };
  },
  methods: {
    async exportCsv(): Promise<void> {
      if (this.loading || this.disabled) return;
      this.loading = true;
      try {
        const { blob, filename } = await patientService.exportCsv(this.params);
        saveBlob(blob, filename);
        // Polite announcement (success toasts are role="status").
        notify(this.$root, "success", `Exportação concluída: ${filename}`);
        this.$emit("exported", filename);
      } catch (e) {
        notify(
          this.$root,
          "error",
          getErrorMessage(e, "Não foi possível exportar os pacientes.")
        );
      } finally {
        this.loading = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.export-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;

  &.is-busy {
    cursor: progress;
  }
}
</style>
