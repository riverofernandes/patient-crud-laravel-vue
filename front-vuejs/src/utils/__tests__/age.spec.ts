import { describe, expect, it } from "vitest";
import {
  ageFromBirthDate,
  formatAge,
  formatDateTime,
  patientAge,
} from "../format";

describe("age helpers", () => {
  it("counts full years, the birthday itself included", () => {
    expect(ageFromBirthDate("1996-07-20", "2026-07-19")).toBe(29);
    expect(ageFromBirthDate("1996-07-20", "2026-07-20")).toBe(30);
    expect(ageFromBirthDate("2000-02-29", "2025-02-28")).toBe(24);
    expect(ageFromBirthDate("2030-01-01", "2026-01-01")).toBeNull();
    expect(ageFromBirthDate("", "2026-01-01")).toBeNull();
  });

  it("formats singular/plural", () => {
    expect(formatAge(0)).toBe("Menos de 1 ano");
    expect(formatAge(1)).toBe("1 ano");
    expect(formatAge(2)).toBe("2 anos");
    expect(formatAge(null)).toBe("");
  });

  it("prefers the API age and falls back to the birth date", () => {
    expect(patientAge({ age: 41, birth_date: "1990-01-01" })).toBe("41 anos");
    expect(patientAge({ birth_date: "1900-01-01" })).toMatch(/^\d+ anos$/);
  });

  it("formats an ISO datetime as DD/MM/AAAA HH:mm (local time)", () => {
    const local = new Date(2026, 9, 5, 9, 7);
    expect(formatDateTime(local.toISOString())).toBe("05/10/2026 09:07");
    expect(formatDateTime(null)).toBe("");
    expect(formatDateTime("nope")).toBe("");
  });
});
