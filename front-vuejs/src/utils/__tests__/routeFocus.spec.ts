import { describe, expect, it } from "vitest";
import { shouldMoveFocus } from "../routeFocus";

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
