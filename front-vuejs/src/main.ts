import Vue from "vue";
import {
  ButtonPlugin,
  FormDatepickerPlugin,
  FormInputPlugin,
  FormSelectPlugin,
  FormTextareaPlugin,
  ModalPlugin,
  PaginationPlugin,
  SpinnerPlugin,
  TablePlugin,
  ToastPlugin,
  TooltipPlugin,
  BIconCalendar,
  BIconCheck,
  BIconChevronLeft,
  BIconChevronRight,
  BIconEyeFill,
  BIconPencilFill,
  BIconPeople,
  BIconSearch,
  BIconTrashFill,
  BIconX,
} from "bootstrap-vue";

import App from "./App.vue";
import router from "./router";

import "./styles/main.scss";

// Register only the BootstrapVue plugins we use (smaller bundle than the full BootstrapVue).
[
  ButtonPlugin,
  FormDatepickerPlugin,
  FormInputPlugin,
  FormSelectPlugin,
  FormTextareaPlugin,
  ModalPlugin,
  PaginationPlugin,
  SpinnerPlugin,
  TablePlugin,
  ToastPlugin,
  TooltipPlugin,
].forEach((plugin) => Vue.use(plugin));

// Register only the icons we use (keeps the bundle small).
const icons = {
  BIconCalendar,
  BIconCheck,
  BIconChevronLeft,
  BIconChevronRight,
  BIconEyeFill,
  BIconPencilFill,
  BIconPeople,
  BIconSearch,
  BIconTrashFill,
  BIconX,
};
Object.entries(icons).forEach(([name, component]) =>
  Vue.component(name, component)
);

new Vue({
  router,
  render: (h) => h(App),
}).$mount("#app");
