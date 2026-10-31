import type { Route } from "vue-router";

type RouteLike = Pick<Route, "path" | "matched">;

/** A position for vue-router's scrollBehavior (undefined = don't scroll). */
type Position = { x: number; y: number };
export type ScrollPosition = Position | undefined;

/**
 * Whether a navigation only changed the query/hash of the page already shown
 * (tabs, search, pagination, Back/Forward between them). Never true for the
 * first navigation, whose `from` is the empty START route (path "/").
 */
export function isSamePathNavigation(to: RouteLike, from: RouteLike): boolean {
  return from.matched.length > 0 && to.path === from.path;
}

/** How long Back/Forward waits for the page to grow before restoring. */
export const RESTORE_SCROLL_TIMEOUT = 2000;

/**
 * Resolves with `position` once the document is tall enough to scroll there
 * (pages load their data after the route changes), or after `timeout`.
 */
export function whenScrollable(
  position: Position,
  timeout = RESTORE_SCROLL_TIMEOUT
): Promise<Position> {
  const fits = () =>
    document.documentElement.scrollHeight >= position.y + window.innerHeight;
  return new Promise((resolve) => {
    const started = Date.now();
    const check = () => {
      if (fits() || Date.now() - started >= timeout) resolve(position);
      else setTimeout(check, 50);
    };
    check();
  });
}

/**
 * Router scroll behavior: Back/Forward restore the saved position (once the
 * page has loaded enough content), query-only changes keep the current one,
 * and new pages start at the top.
 */
export function scrollBehavior(
  to: RouteLike,
  from: RouteLike,
  savedPosition?: Position | void | null
): ScrollPosition | Promise<Position> {
  if (savedPosition) return whenScrollable(savedPosition);
  if (isSamePathNavigation(to, from)) return undefined;
  return { x: 0, y: 0 };
}

/**
 * Document title for a navigation, or null to keep the current one: query-only
 * changes keep the title a page set itself (e.g. "Ana Souza | EyeCare").
 */
export function routeTitle(
  to: RouteLike & Pick<Route, "meta">,
  from: RouteLike
): string | null {
  if (isSamePathNavigation(to, from)) return null;
  const title = to.meta?.title as string | undefined;
  return title ? `${title} | EyeCare` : "EyeCare";
}

/**
 * Whether focus should move to the new page after a navigation.
 * - Not on the first load (the browser already starts at the top of the document).
 * - Not when only the query/hash changed (search typing and pagination use the
 *   query string; moving focus there would yank the user out of the search box).
 */
export function shouldMoveFocus(to: RouteLike, from: RouteLike): boolean {
  const isInitial = from.matched.length === 0;
  return !isInitial && !isSamePathNavigation(to, from);
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
