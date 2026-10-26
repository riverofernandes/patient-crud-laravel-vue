import type { Route } from "vue-router";

type RouteLike = Pick<Route, "path" | "matched">;

/**
 * Whether focus should move to the new page after a navigation.
 * - Not on the first load (the browser already starts at the top of the document).
 * - Not when only the query/hash changed (search typing and pagination use the
 *   query string; moving focus there would yank the user out of the search box).
 */
export function shouldMoveFocus(to: RouteLike, from: RouteLike): boolean {
  const isInitial = from.matched.length === 0;
  return !isInitial && to.path !== from.path;
}

/**
 * Focuses the page's <h1> (made programmatically focusable) so screen readers
 * announce the new page; falls back to the container (e.g. while loading).
 */
export function focusPageHeading(container: HTMLElement | null): void {
  if (!container) return;
  const heading = container.querySelector<HTMLElement>("h1");
  const target = heading ?? container;
  if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
  target.focus({ preventScroll: true });
}

/**
 * For views whose <h1> only appears after data loads (detail/edit): move focus to
 * it, but only if focus is still parked on the main container after the route
 * change (never steal it from something the user moved to meanwhile).
 */
export function focusHeadingIfParked(): void {
  const main = document.getElementById("main-content");
  if (main && document.activeElement === main) focusPageHeading(main);
}
