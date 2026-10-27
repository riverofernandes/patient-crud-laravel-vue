import { describe, expect, it } from "vitest";
import {
  AGE_ORDER_MESSAGE,
  AGE_RANGE_MESSAGE,
  activeFilterCount,
  filtersFromQuery,
  filtersToQuery,
  parseAge,
  sameFilters,
  validateAgeRange,
} from "../filters";

describe("filters", () => {
  it("parses ages 0–150 only", () => {
    expect(parseAge("0")).toBe(0);
    expect(parseAge(150)).toBe(150);
    expect(parseAge("151")).toBeNull();
    expect(parseAge("-1")).toBeNull();
    expect(parseAge("1.5")).toBeNull();
    expect(parseAge("abc")).toBeNull();
    expect(parseAge(undefined)).toBeNull();
  });

  it("reads the query ignoring invalid values", () => {
    expect(
      filtersFromQuery({ sex: "F", age_min: "0", age_max: "200" })
    ).toEqual({ sex: "F", ageMin: 0, ageMax: null });
    expect(filtersFromQuery({ sex: "X" })).toEqual({
      sex: "",
      ageMin: null,
      ageMax: null,
    });
  });

  it("writes only active filters (0 is active)", () => {
    expect(filtersToQuery({ sex: "", ageMin: 0, ageMax: null })).toEqual({
      age_min: "0",
    });
    expect(filtersToQuery({ sex: "M", ageMin: 18, ageMax: 60 })).toEqual({
      sex: "M",
      age_min: "18",
      age_max: "60",
    });
    expect(activeFilterCount({ sex: "M", ageMin: 0, ageMax: null })).toBe(2);
    expect(
      sameFilters(
        { sex: "M", ageMin: 1, ageMax: null },
        { sex: "M", ageMin: 1, ageMax: null }
      )
    ).toBe(true);
  });

  it("validates range and order", () => {
    expect(validateAgeRange("", "")).toEqual({ ageMin: "", ageMax: "" });
    expect(validateAgeRange("200", "")).toEqual({
      ageMin: AGE_RANGE_MESSAGE,
      ageMax: "",
    });
    expect(validateAgeRange("50", "20")).toEqual({
      ageMin: "",
      ageMax: AGE_ORDER_MESSAGE,
    });
    expect(validateAgeRange("20", "20").ageMax).toBe("");
  });
});
