<template>
  <PageState
    variant="not-found"
    title="Página não encontrada"
    message="O endereço acessado não existe."
  >
    <b-button variant="primary" :to="{ name: 'patients.index' }"
      >Voltar para pacientes</b-button
    >
  </PageState>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import PageState from "@/components/PageState.vue";

export default defineComponent({
  name: "NotFoundView",
  components: { PageState },
});
</script>
