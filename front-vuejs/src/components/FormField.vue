<template>
  <div class="form-field" :class="{ 'has-error': !!error }">
    <label :for="id" class="form-field__label">
      {{ label }}
      <small v-if="optional" class="form-field__optional">(Opcional)</small>
    </label>
    <slot />
    <div class="form-field__feedback">
      <span
        v-if="error"
        :id="`${id}-error`"
        class="form-field__error"
        role="alert"
      >
        {{ error }}
      </span>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue";

export default defineComponent({
  name: "FormField",
  props: {
    id: { type: String, required: true },
    label: { type: String, required: true },
    optional: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.form-field {
  min-width: 0;
}

.form-field__label {
  display: block;
  margin-bottom: 8px;
  color: $navy;
  font-size: 16px;
  font-weight: 600;
  line-height: 20px;
}

.form-field__optional {
  margin-left: 6px;
  color: $text-muted;
  font-size: 11px;
  font-weight: 400;
}

.form-field__feedback {
  min-height: 24px;
  padding-top: 3px;
  text-align: right;
}

.form-field__error {
  display: block;
  color: $red;
  font-size: 12px;
  line-height: 16px;
}
</style>
