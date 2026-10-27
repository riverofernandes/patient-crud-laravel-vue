import { createLocalVue } from "@vue/test-utils";
import {
  BootstrapVue,
  BIconArrowCounterclockwise,
  BIconArrowDownUp,
  BIconArrowRight,
  BIconCalendar,
  BIconCaretDownFill,
  BIconCaretUpFill,
  BIconCheck,
  BIconChevronLeft,
  BIconChevronRight,
  BIconClockHistory,
  BIconDownload,
  BIconEyeFill,
  BIconFunnel,
  BIconPencil,
  BIconPencilFill,
  BIconPersonPlus,
  BIconSearch,
  BIconTrash,
  BIconTrashFill,
  BIconX,
  BIconCalendarCheck,
  BIconCalendarPlus,
  BIconCalendarWeek,
  BIconCheckCircle,
  BIconCircle,
  BIconClipboardCheck,
  BIconClock,
  BIconDash,
  BIconFileEarmarkMedical,
  BIconFileEarmarkPdf,
  BIconPersonX,
  BIconPlus,
  BIconXCircle,
  BIconCamera,
  BIconCloudUpload,
  BIconFileEarmark,
  BIconFileEarmarkImage,
  BIconPaperclip,
  BIconUpload,
} from "bootstrap-vue";
import VueRouter from "vue-router";

/** A local Vue with BootstrapVue, the icons the app registers globally and the router. */
export function makeLocalVue() {
  const localVue = createLocalVue();
  localVue.use(BootstrapVue);
  localVue.use(VueRouter);
  Object.entries({
    BIconArrowCounterclockwise,
    BIconArrowDownUp,
    BIconArrowRight,
    BIconCalendar,
    BIconCaretDownFill,
    BIconCaretUpFill,
    BIconCheck,
    BIconChevronLeft,
    BIconChevronRight,
    BIconClockHistory,
    BIconDownload,
    BIconEyeFill,
    BIconFunnel,
    BIconPencil,
    BIconPencilFill,
    BIconPersonPlus,
    BIconSearch,
    BIconTrash,
    BIconTrashFill,
    BIconX,
    BIconCalendarCheck,
    BIconCalendarPlus,
    BIconCalendarWeek,
    BIconCheckCircle,
    BIconCircle,
    BIconClipboardCheck,
    BIconClock,
    BIconDash,
    BIconFileEarmarkMedical,
    BIconFileEarmarkPdf,
    BIconPersonX,
    BIconPlus,
    BIconXCircle,
    BIconCamera,
    BIconCloudUpload,
    BIconFileEarmark,
    BIconFileEarmarkImage,
    BIconPaperclip,
    BIconUpload,
  }).forEach(([name, component]) => localVue.component(name, component));
  return localVue;
}

/** Drains pending promise callbacks (works with fake timers, unlike setTimeout). */
export async function flushPromises(rounds = 10): Promise<void> {
  for (let i = 0; i < rounds; i++) await Promise.resolve();
}

const Blank = { render: (h: (tag: string) => unknown) => h("div") };

/** In-memory router with the app's route names (targets are blank stubs). */
export function makeRouter(): VueRouter {
  return new VueRouter({
    mode: "abstract",
    routes: [
      { path: "/", name: "patients.index", component: Blank },
      { path: "/pacientes/novo", name: "patients.create", component: Blank },
      { path: "/pacientes/lixeira", name: "patients.trash", component: Blank },
      { path: "/pacientes/:id", name: "patients.show", component: Blank },
      {
        path: "/pacientes/:id/editar",
        name: "patients.edit",
        component: Blank,
      },
      {
        path: "/pacientes/:id/atendimentos/novo",
        name: "consultations.create",
        component: Blank,
      },
      {
        path: "/atendimentos/:id",
        name: "consultations.show",
        component: Blank,
      },
      { path: "/agenda", name: "agenda", component: Blank },
    ],
  });
}
