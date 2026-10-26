<template>
  <div class="app-layout">
    <a class="skip-link" href="#main-content">Pular para o conteúdo</a>

    <aside class="app-sidebar" aria-label="Menu principal">
      <nav>
        <router-link
          to="/"
          class="app-sidebar__link"
          :class="{ 'is-active': isPatientsSection }"
          aria-label="Pacientes"
          title="Pacientes"
        >
          <b-icon-people aria-hidden="true" />
        </router-link>
      </nav>
    </aside>

    <div class="app-main">
      <header class="app-topbar" />
      <main id="main-content" class="app-content" tabindex="-1">
        <slot />
      </main>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue";

export default defineComponent({
  name: "AppLayout",
  computed: {
    isPatientsSection(): boolean {
      const name = this.$route.name ?? "";
      return name.startsWith("patients.");
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.app-layout {
  display: flex;
  min-height: 100vh;
}

.skip-link {
  position: absolute;
  top: -40px;
  left: 8px;
  z-index: 2000;
  padding: 8px 12px;
  color: #fff;
  background: $blue;
  border-radius: 4px;

  &:focus {
    top: 8px;
  }
}

.app-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  z-index: 1045; // above the modal backdrop (1040), like the mockup
  display: flex;
  flex-direction: column;
  align-items: center;
  width: $sidebar-width;
  padding-top: 88px;
  background: $navy;
}

.app-sidebar__link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  color: rgba(#fff, 0.55);
  font-size: 22px;
  border-radius: 8px;
  transition: background-color 0.15s, color 0.15s;

  &:hover,
  &.is-active {
    color: #fff;
    background: rgba(#fff, 0.1);
  }
}

.app-main {
  flex: 1;
  min-width: 0;
  margin-left: $sidebar-width;
}

.app-topbar {
  height: $topbar-height;
  background: #fff;
  border-bottom: 1px solid $border-light;
}

.app-content {
  padding: 24px 24px 30px;

  &:focus {
    outline: none;
  }
}

@media (max-width: 575.98px) {
  .app-sidebar {
    display: none;
  }

  .app-main {
    margin-left: 0;
  }

  .app-topbar {
    height: 56px;
    background: $navy;
    border-bottom: 0;
  }

  .app-content {
    padding: 16px;
  }
}
</style>
