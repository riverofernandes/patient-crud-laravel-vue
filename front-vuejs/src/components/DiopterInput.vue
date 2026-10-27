<template>
  <div class="diopter-input" :class="{ 'is-disabled': disabled }">
    <button
      type="button"
      class="diopter-input__step"
      tabindex="-1"
      :disabled="disabled"
      :aria-label="`Diminuir ${label} em 0.25`"
      @click="step(-1)"
    >
      <b-icon-dash aria-hidden="true" />
    </button>
    <input
      :id="id"
      class="form-control diopter-input__control"
      :class="{ 'is-invalid': invalid }"
      type="text"
      inputmode="decimal"
      autocomplete="off"
      maxlength="7"
      :value="value"
      :disabled="disabled"
      :placeholder="placeholder"
      :aria-invalid="invalid ? 'true' : undefined"
      :aria-describedby="describedby || undefined"
      @input="onInput"
      @blur="onBlur"
      @keydown.up.prevent="step(1)"
      @keydown.down.prevent="step(-1)"
    />
    <button
      type="button"
      class="diopter-input__step"
      tabindex="-1"
      :disabled="disabled"
      :aria-label="`Aumentar ${label} em 0.25`"
      @click="step(1)"
    >
      <b-icon-plus aria-hidden="true" />
    </button>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import {
  normalizeDiopterText,
  stepDiopter,
  type DiopterField,
} from "@/utils/eyeExam";

/**
 * Diopter text field (+1.25 / −0.50) with 0.25 steps: arrow keys up/down or
 * the −/+ buttons (out of the tab order: the arrow keys cover keyboard use).
 * The value is re-formatted with its sign on blur.
 */
export default defineComponent({
  name: "DiopterInput",
  props: {
    id: { type: String, required: true },
    value: { type: String, default: "" },
    field: { type: String as PropType<DiopterField>, required: true },
    /** Accessible name used by the step buttons, e.g. "esférico do olho direito". */
    label: { type: String, required: true },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    describedby: { type: String, default: "" },
    placeholder: { type: String, default: "—" },
  },
  methods: {
    step(direction: 1 | -1): void {
      if (this.disabled) return;
      this.$emit("input", stepDiopter(this.value, direction, this.field));
    },
    onInput(event: Event): void {
      this.$emit("input", (event.target as HTMLInputElement).value);
    },
    onBlur(): void {
      const formatted = normalizeDiopterText(this.value);
      if (formatted !== this.value) this.$emit("input", formatted);
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.diopter-input {
  display: flex;
  align-items: stretch;
}

.diopter-input__control {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0 4px;
  font-variant-numeric: tabular-nums;
  text-align: center;
  border-radius: 0;

  &.is-invalid {
    padding-right: 4px;
  }
}

.diopter-input__step {
  display: inline-flex;
  flex: 0 0 36px;
  align-items: center;
  justify-content: center;
  height: $input-height;
  padding: 0;
  color: $navy;
  font-size: 16px;
  background: $page-bg;
  border: 1px solid $border;

  &:first-child {
    border-right: 0;
    border-radius: $border-radius 0 0 $border-radius;
  }

  &:last-child {
    border-left: 0;
    border-radius: 0 $border-radius $border-radius 0;
  }

  &:hover:not(:disabled) {
    color: $blue;
    background: darken($page-bg, 3%);
  }

  &:disabled {
    color: $text-muted;
    cursor: not-allowed;
  }
}
</style>
