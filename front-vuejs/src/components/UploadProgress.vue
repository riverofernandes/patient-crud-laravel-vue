<template>
  <div class="upload-progress">
    <div
      class="upload-progress__track"
      role="progressbar"
      :aria-label="label"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="percent"
      :aria-valuetext="`${percent}%`"
    >
      <div class="upload-progress__bar" :style="{ width: `${percent}%` }" />
    </div>
    <span class="upload-progress__text" aria-hidden="true"
      >{{ label }}… {{ percent }}%</span
    >
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue";

/** Upload progress bar (no BootstrapVue plugin needed). */
export default defineComponent({
  name: "UploadProgress",
  props: {
    value: { type: Number, default: 0 },
    label: { type: String, default: "Enviando" },
  },
  computed: {
    percent(): number {
      return Math.max(0, Math.min(100, Math.round(this.value || 0)));
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.upload-progress {
  display: flex;
  gap: 12px;
  align-items: center;
}

.upload-progress__track {
  flex: 1 1 auto;
  height: 8px;
  overflow: hidden;
  background: $border-light;
  border-radius: 4px;
}

.upload-progress__bar {
  height: 100%;
  background: $blue;
  transition: width 0.2s ease;
}

.upload-progress__text {
  flex: 0 0 auto;
  color: $text;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
}
</style>
