<template>
  <!-- Decorative (listing): hidden from assistive tech and with NO text
       content, so the name cell's text stays exactly the patient's name. The
       initials are drawn by CSS from data-initials. -->
  <span
    class="patient-avatar"
    :class="[`patient-avatar--${size}`, { 'has-photo': showPhoto }]"
    :data-initials="showPhoto ? undefined : initials"
    :aria-hidden="decorative || !showPhoto ? 'true' : undefined"
    ><img
      v-if="showPhoto"
      :src="photoUrl || undefined"
      :alt="decorative ? '' : `Foto de ${name}`"
      class="patient-avatar__img"
      loading="lazy"
      decoding="async"
      @error="failed = true"
  /></span>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import { initials } from "@/utils/files";

/**
 * Patient photo, or the initials when there is none (or it fails to load).
 * Plain <img> on purpose: the API serves photos cross-origin without auth.
 */
export default defineComponent({
  name: "PatientAvatar",
  props: {
    name: { type: String, required: true },
    photoUrl: { type: String as PropType<string | null>, default: null },
    size: {
      type: String as PropType<"sm" | "lg">,
      default: "sm",
    },
    /** Next to the visible name (listing): no alternative text. */
    decorative: { type: Boolean, default: false },
  },
  data() {
    return { failed: false };
  },
  computed: {
    initials(): string {
      return initials(this.name);
    },
    showPhoto(): boolean {
      return !!this.photoUrl && !this.failed;
    },
  },
  watch: {
    photoUrl() {
      this.failed = false;
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-avatar {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: $navy;
  font-weight: 700;
  line-height: 1;
  vertical-align: middle;
  background: $page-bg;
  border: 1px solid $border;
  border-radius: 50%;

  &::before {
    content: attr(data-initials);
  }

  &.has-photo::before {
    content: none;
  }
}

.patient-avatar--sm {
  width: 32px;
  height: 32px;
  font-size: 12px;
}

.patient-avatar--lg {
  width: 96px;
  height: 96px;
  font-size: 32px;
}

.patient-avatar__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

@media (max-width: 575.98px) {
  .patient-avatar--lg {
    width: 72px;
    height: 72px;
    font-size: 24px;
  }
}
</style>
