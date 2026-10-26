import Vue from "vue";
import VueRouter from "vue-router";
import PatientListView from "@/views/PatientListView.vue";

Vue.use(VueRouter);

const router = new VueRouter({
  mode: "history",
  base: import.meta.env.BASE_URL,
  routes: [
    {
      path: "/",
      name: "patients.index",
      component: PatientListView,
      meta: { title: "Pacientes" },
    },
    {
      path: "/pacientes/novo",
      name: "patients.create",
      component: () => import("@/views/PatientCreateView.vue"),
      meta: { title: "Novo paciente" },
    },
    {
      path: "/pacientes/lixeira",
      name: "patients.trash",
      component: () => import("@/views/PatientTrashView.vue"),
      meta: { title: "Pacientes excluídos" },
    },
    {
      path: "/pacientes/:id(\\d+)",
      name: "patients.show",
      component: () => import("@/views/PatientDetailView.vue"),
      props: true,
      meta: { title: "Paciente" },
    },
    {
      path: "/pacientes/:id(\\d+)/editar",
      name: "patients.edit",
      component: () => import("@/views/PatientEditView.vue"),
      props: true,
      meta: { title: "Editar paciente" },
    },
    {
      path: "*",
      name: "not-found",
      component: () => import("@/views/NotFoundView.vue"),
      meta: { title: "Página não encontrada" },
    },
  ],
  scrollBehavior: () => ({ x: 0, y: 0 }),
});

router.afterEach((to) => {
  const title = to.meta?.title as string | undefined;
  document.title = title ? `${title} | EyeCare` : "EyeCare";
});

export default router;
