<template>
  <nav class="app-breadcrumb" aria-label="Navegação estrutural">
    <ol>
      <li v-for="(item, index) in items" :key="index">
        <router-link v-if="item.to && index < items.length - 1" :to="item.to">
          {{ item.text }}
        </router-link>
        <span v-else aria-current="page">{{ item.text }}</span>
        <b-icon-chevron-right
          v-if="index < items.length - 1"
          class="app-breadcrumb__sep"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import type { RawLocation } from "vue-router";

export interface BreadcrumbItem {
  text: string;
  to?: RawLocation;
}

export default defineComponent({
  name: "AppBreadcrumb",
  props: {
    items: {
      type: Array as PropType<BreadcrumbItem[]>,
      required: true,
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.app-breadcrumb {
  margin-bottom: 32px;
  padding-top: 8px;

  ol {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  li {
    display: inline-flex;
    align-items: center;
    font-size: 16px;
    font-weight: 600;
    line-height: 24px;
  }

  a {
    color: $blue;
  }

  span {
    color: $text;
  }
}

.app-breadcrumb__sep {
  margin: 0 14px;
  color: $text-muted;
  font-size: 14px;
}
</style>
