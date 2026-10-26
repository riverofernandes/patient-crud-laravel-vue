<template>
  <div
    class="app-card page-state"
    :role="variant === 'loading' ? 'status' : undefined"
  >
    <b-spinner v-if="variant === 'loading'" variant="primary" class="mb-3" />
    <!-- not-found/error replace the whole page, so their title is the page's h1;
         the loading title is transient (focus must never land on it). -->
    <p v-if="variant === 'loading'" class="state-box__title">{{ title }}</p>
    <h1 v-else class="state-box__title page-state__title">{{ title }}</h1>
    <p v-if="message" class="mb-0">{{ message }}</p>
    <div v-if="$slots.default" class="page-state__actions">
      <slot />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";

export default defineComponent({
  name: "PageState",
  props: {
    variant: {
      type: String as PropType<"loading" | "not-found" | "error">,
      default: "loading",
    },
    title: { type: String, required: true },
    message: { type: String, default: "" },
  },
});
</script>

<style lang="scss" scoped>
.page-state__title {
  letter-spacing: normal;
}

.page-state {
  padding: 64px 24px;
  text-align: center;
}

.page-state__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: center;
  margin-top: 24px;
}
</style>
