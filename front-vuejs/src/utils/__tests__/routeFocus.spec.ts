// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  isSamePathNavigation,
  routeTitle,
  scrollBehavior,
  shouldMoveFocus,
  whenScrollable,
} from "../routeFocus";

const route = (path: string, matched = 1) =>
  ({ path, matched: Array.from({ length: matched }) } as never);

describe("shouldMoveFocus", () => {
  it("does not move focus on the initial navigation", () => {
    expect(shouldMoveFocus(route("/"), route("/", 0))).toBe(false);
  });

  it("moves focus when the path changes", () => {
    expect(shouldMoveFocus(route("/pacientes/novo"), route("/"))).toBe(true);
    expect(
      shouldMoveFocus(route("/pacientes/1/editar"), route("/pacientes/1"))
    ).toBe(true);
  });

  it("keeps focus when only the query changes (search, pagination)", () => {
    expect(shouldMoveFocus(route("/"), route("/"))).toBe(false);
  });
});

describe("isSamePathNavigation", () => {
  it("is false on the initial navigation, even to /", () => {
    expect(isSamePathNavigation(route("/"), route("/", 0))).toBe(false);
  });

  it("is true for query-only changes and false for new paths", () => {
    expect(
      isSamePathNavigation(route("/pacientes/1"), route("/pacientes/1"))
    ).toBe(true);
    expect(
      isSamePathNavigation(route("/pacientes/2"), route("/pacientes/1"))
    ).toBe(false);
  });
});

describe("scrollBehavior", () => {
  it("restores the saved position on Back/Forward", async () => {
    await expect(
      scrollBehavior(route("/"), route("/pacientes/1"), { x: 0, y: 0 })
    ).resolves.toEqual({ x: 0, y: 0 });
  });

  it("keeps the position on query-only changes (tabs, sort, pagination)", () => {
    expect(scrollBehavior(route("/"), route("/"), null)).toBeUndefined();
  });

  it("starts new pages at the top", () => {
    expect(scrollBehavior(route("/pacientes/1"), route("/"), null)).toEqual({
      x: 0,
      y: 0,
    });
    expect(scrollBehavior(route("/"), route("/", 0), null)).toEqual({
      x: 0,
      y: 0,
    });
  });
});

describe("routeTitle", () => {
  const withTitle = (path: string, title?: string) =>
    Object.assign(route(path), { meta: title ? { title } : {} }) as never;

  it("uses the route title on path changes and the initial load", () => {
    expect(routeTitle(withTitle("/", "Pacientes"), route("/", 0))).toBe(
      "Pacientes | EyeCare"
    );
    expect(routeTitle(withTitle("/pacientes/1", "Paciente"), route("/"))).toBe(
      "Paciente | EyeCare"
    );
    expect(routeTitle(withTitle("/x"), route("/"))).toBe("EyeCare");
  });

  it("keeps the current (dynamic) title on query-only changes", () => {
    expect(
      routeTitle(withTitle("/pacientes/1", "Paciente"), route("/pacientes/1"))
    ).toBeNull();
  });
});

describe("whenScrollable", () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("waits until the page is tall enough, then resolves", async () => {
    vi.useFakeTimers();
    let height = 100;
    vi.spyOn(
      document.documentElement,
      "scrollHeight",
      "get"
    ).mockImplementation(() => height);
    const done = vi.fn();
    void whenScrollable({ x: 0, y: 900 }).then(done);
    vi.advanceTimersByTime(200);
    await Promise.resolve();
    expect(done).not.toHaveBeenCalled();
    height = 5000;
    vi.advanceTimersByTime(50);
    await Promise.resolve();
    expect(done).toHaveBeenCalledWith({ x: 0, y: 900 });
  });

  it("gives up after the timeout", async () => {
    vi.useFakeTimers();
    vi.spyOn(document.documentElement, "scrollHeight", "get").mockReturnValue(
      0
    );
    const done = vi.fn();
    void whenScrollable({ x: 0, y: 900 }, 300).then(done);
    vi.advanceTimersByTime(350);
    await Promise.resolve();
    expect(done).toHaveBeenCalled();
  });
});
