import type Vue from "vue";

export type ToastVariant = "success" | "error";

export const TOAST_DURATION = 4000;

let counter = 0;
let currentId: string | null = null;

/**
 * Shows a toast styled like the mockups (icon tile, message, close button and
 * a shrinking progress bar). Pass the ROOT instance (`this.$root`) so the toast
 * survives route changes.
 */
export function notify(
  rootInstance: object,
  variant: ToastVariant,
  message: string
): void {
  const root = rootInstance as Vue;
  const h = root.$createElement;
  // One toast at a time (as in the mockups): dismiss the previous one first.
  if (currentId) root.$bvToast.hide(currentId);
  const id = `app-toast-${++counter}`;
  currentId = id;
  const icon = variant === "success" ? "check" : "x";

  const content = h("div", { class: "app-toast__content" }, [
    h("span", { class: "app-toast__icon", attrs: { "aria-hidden": "true" } }, [
      h("span", { class: "app-toast__icon-inner" }, [h(`b-icon-${icon}`)]),
    ]),
    h("span", { class: "app-toast__message" }, message),
    h(
      "button",
      {
        class: "app-toast__close",
        attrs: { type: "button", "aria-label": "Fechar notificação" },
        on: { click: () => root.$bvToast.hide(id) },
      },
      [h("b-icon-x", { attrs: { "aria-hidden": "true" } })]
    ),
  ]);

  root.$bvToast.toast([content], {
    id,
    toaster: "b-toaster-top-right",
    autoHideDelay: TOAST_DURATION,
    noCloseButton: true,
    solid: true,
    appendToast: false,
    // Success is informational (role="status", polite); errors interrupt (role="alert").
    isStatus: variant === "success",
    toastClass: ["app-toast", `app-toast--${variant}`],
    bodyClass: "app-toast__body",
  });
}
