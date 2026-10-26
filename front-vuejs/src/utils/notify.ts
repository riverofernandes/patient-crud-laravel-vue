import type Vue from "vue";
import type { VNode } from "vue";
import { focusPageHeading } from "./routeFocus";

export type ToastVariant = "success" | "error";

export const TOAST_DURATION = 4000;
/** Toasts with an action (e.g. "Desfazer") stay longer, and pause on hover/focus. */
export const ACTION_TOAST_DURATION = 10000;

export interface ToastAction {
  label: string;
  /** Accessible name when the visible label needs context (e.g. "Desfazer exclusão"). */
  ariaLabel?: string;
  handler: () => void;
}

export interface NotifyOptions {
  action?: ToastAction;
}

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
  message: string,
  options: NotifyOptions = {}
): void {
  const root = rootInstance as Vue;
  const h = root.$createElement;
  // One toast at a time (as in the mockups): dismiss the previous one first.
  if (currentId) root.$bvToast.hide(currentId);
  const id = `app-toast-${++counter}`;
  currentId = id;
  const icon = variant === "success" ? "check" : "x";
  const { action } = options;
  const duration = action ? ACTION_TOAST_DURATION : TOAST_DURATION;

  // Action toasts manage their own timer so keyboard focus pauses it too
  // (BootstrapVue only pauses on mouse hover). WCAG 2.2.1 Timing Adjustable.
  let timer: ReturnType<typeof setTimeout> | undefined;
  let remaining = duration;
  let startedAt = 0;
  let hovered = false;
  let focused = false;
  let paused = true;
  const hide = () => {
    if (timer) clearTimeout(timer);
    root.$bvToast.hide(id);
  };
  const sync = (el?: Element | null) => {
    const shouldPause = hovered || focused;
    if (shouldPause && !paused) {
      if (timer) clearTimeout(timer);
      remaining -= Date.now() - startedAt;
      paused = true;
    } else if (!shouldPause && paused) {
      startedAt = Date.now();
      timer = setTimeout(hide, Math.max(remaining, 0));
      paused = false;
    }
    el?.closest(".app-toast")?.classList.toggle("is-paused", shouldPause);
  };

  const children: VNode[] = [
    h("span", { class: "app-toast__icon", attrs: { "aria-hidden": "true" } }, [
      h("span", { class: "app-toast__icon-inner" }, [h(`b-icon-${icon}`)]),
    ]),
    h("span", { class: "app-toast__message" }, message),
  ];
  if (action) {
    children.push(
      h(
        "button",
        {
          class: "app-toast__action",
          attrs: { type: "button", "aria-label": action.ariaLabel },
          on: {
            click: (event: Event) => {
              const toast = (event.currentTarget as Element).closest(
                ".app-toast"
              );
              const hadFocus = !!toast?.contains(document.activeElement);
              hide();
              // The button is about to disappear: don't drop focus on <body>.
              if (hadFocus)
                focusPageHeading(document.getElementById("main-content"));
              action.handler();
            },
          },
        },
        action.label
      )
    );
  }
  children.push(
    h(
      "button",
      {
        class: "app-toast__close",
        attrs: { type: "button", "aria-label": "Fechar notificação" },
        on: { click: hide },
      },
      [h("b-icon-x", { attrs: { "aria-hidden": "true" } })]
    ),
    h("span", {
      class: "app-toast__progress",
      style: { animationDuration: `${duration}ms` },
      attrs: { "aria-hidden": "true" },
    })
  );

  const content = h(
    "div",
    {
      class: "app-toast__content",
      on: action
        ? {
            mouseenter: (e: Event) => {
              hovered = true;
              sync(e.currentTarget as Element);
            },
            mouseleave: (e: Event) => {
              hovered = false;
              sync(e.currentTarget as Element);
            },
            focusin: (e: Event) => {
              focused = true;
              sync(e.currentTarget as Element);
            },
            focusout: (e: FocusEvent) => {
              const el = e.currentTarget as Element;
              focused = el.contains(e.relatedTarget as Node | null);
              sync(el);
            },
          }
        : {},
    },
    children
  );

  root.$bvToast.toast([content], {
    id,
    toaster: "b-toaster-top-right",
    autoHideDelay: duration,
    noAutoHide: !!action,
    noHoverPause: !!action,
    noCloseButton: true,
    solid: true,
    appendToast: false,
    // Success is informational (role="status", polite); errors interrupt (role="alert").
    isStatus: variant === "success",
    toastClass: [
      "app-toast",
      `app-toast--${variant}`,
      ...(action ? ["app-toast--action"] : []),
    ],
    bodyClass: "app-toast__body",
  });

  if (action) sync();
}
