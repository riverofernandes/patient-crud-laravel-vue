<template>
  <div class="detail-item">
    <dt class="detail-item__label">{{ label }}</dt>
    <dd
      class="detail-item__value"
      :class="{ 'is-multiline': multiline, 'is-empty': !value }"
    >
      {{ value || "Não informado" }}
    </dd>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";

export default defineComponent({
  name: "DetailItem",
  props: {
    label: { type: String, required: true },
    value: { type: String as PropType<string | null>, default: "" },
    multiline: { type: Boolean, default: false },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.detail-item {
  min-width: 0;
  margin-bottom: 24px;
}

.detail-item__label {
  margin-bottom: 8px;
  color: $navy;
  font-size: 16px;
  font-weight: 600;
  line-height: 20px;
}

.detail-item__value {
  display: flex;
  align-items: center;
  min-height: 40px;
  margin: 0;
  padding: 8px 15px;
  overflow-wrap: anywhere;
  color: $text;
  font-size: 16px;
  line-height: 24px;
  background: $page-bg;
  border-radius: $border-radius;

  &.is-multiline {
    min-height: 56px;
    white-space: pre-line;
  }

  &.is-empty {
    color: $text-muted;
    font-style: italic;
  }
}
</style>
