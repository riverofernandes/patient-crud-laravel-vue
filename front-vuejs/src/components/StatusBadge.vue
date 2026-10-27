<template>
  <span class="status-badge" :class="`status-badge--${status}`">
    <component :is="icon" class="status-badge__icon" aria-hidden="true" />
    <span v-if="srPrefix" class="sr-only">{{ srPrefix }} </span>{{ label }}
  </span>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import { statusIcon, statusLabel } from "@/utils/consultationStatus";

/**
 * Consultation status as text + icon + color (never color alone). Colors are
 * dark text on a tinted background (≥ 4.5:1).
 */
export default defineComponent({
  name: "StatusBadge",
  props: {
    status: { type: String, required: true },
    /** Visually hidden prefix for context, e.g. "Status:". */
    srPrefix: { type: String, default: "" },
  },
  computed: {
    label(): string {
      return statusLabel(this.status);
    },
    icon(): string {
      return statusIcon(this.status);
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.status-badge {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 6px;
  align-items: center;
  padding: 2px 10px;
  font-size: 13px;
  font-weight: 700;
  line-height: 20px;
  white-space: nowrap;
  border: 1px solid transparent;
  border-radius: 999px;

  &--scheduled {
    color: #1d3fae;
    background: #e6ecfd;
    border-color: #c4d2fa;
  }

  &--confirmed {
    color: #0a5c63;
    background: #ddf4f5;
    border-color: #a9e0e3;
  }

  &--completed {
    color: #0b6b3c;
    background: #dcf7e9;
    border-color: #a6e6c5;
  }

  &--no_show {
    color: #8a4300;
    background: #fff0e0;
    border-color: #f7cf9f;
  }

  &--cancelled {
    color: #4a5578;
    background: #eef0f5;
    border-color: #d3d8e5;
  }
}

.status-badge__icon {
  flex: 0 0 auto;
  font-size: 14px;
}
</style>
