/**
 * Listing filters (sex / age range) <-> query string, and their validation.
 */
import type { Sex } from "@/services/patientService";

export const AGE_MIN_LIMIT = 0;
export const AGE_MAX_LIMIT = 150;

export interface PatientFilterValues {
  sex: Sex | "";
  ageMin: number | null;
  ageMax: number | null;
}

export const EMPTY_FILTERS: Readonly<PatientFilterValues> = Object.freeze({
  sex: "",
  ageMin: null,
  ageMax: null,
});

/** Whole number 0–150 (string or number), otherwise null. */
export function parseAge(value: unknown): number | null {
  const text = typeof value === "number" ? String(value) : value;
  if (typeof text !== "string" || !/^\d{1,3}$/.test(text.trim())) return null;
  const age = Number(text);
  return age >= AGE_MIN_LIMIT && age <= AGE_MAX_LIMIT ? age : null;
}

export function parseSex(value: unknown): Sex | "" {
  return value === "M" || value === "F" ? value : "";
}

type QueryLike = Record<string, unknown>;

/** Reads `sex`, `age_min` and `age_max`, ignoring invalid values (like the API). */
export function filtersFromQuery(query: QueryLike): PatientFilterValues {
  return {
    sex: parseSex(query.sex),
    ageMin: parseAge(query.age_min),
    ageMax: parseAge(query.age_max),
  };
}

/** Query params for the active filters only. */
export function filtersToQuery(
  filters: PatientFilterValues
): Record<string, string> {
  const query: Record<string, string> = {};
  if (filters.sex) query.sex = filters.sex;
  if (filters.ageMin !== null) query.age_min = String(filters.ageMin);
  if (filters.ageMax !== null) query.age_max = String(filters.ageMax);
  return query;
}

export function activeFilterCount(filters: PatientFilterValues): number {
  return (
    (filters.sex ? 1 : 0) +
    (filters.ageMin !== null ? 1 : 0) +
    (filters.ageMax !== null ? 1 : 0)
  );
}

export function sameFilters(
  a: PatientFilterValues,
  b: PatientFilterValues
): boolean {
  return a.sex === b.sex && a.ageMin === b.ageMin && a.ageMax === b.ageMax;
}

export const AGE_RANGE_MESSAGE = `Informe uma idade entre ${AGE_MIN_LIMIT} e ${AGE_MAX_LIMIT}.`;
export const AGE_ORDER_MESSAGE =
  "A idade máxima deve ser maior ou igual à mínima.";

export interface AgeErrors {
  ageMin: string;
  ageMax: string;
}

/** Validates the raw inputs ('' = no limit). The order error goes on the max field. */
export function validateAgeRange(ageMin: string, ageMax: string): AgeErrors {
  const errors: AgeErrors = { ageMin: "", ageMax: "" };
  const min = ageMin.trim() === "" ? null : parseAge(ageMin);
  const max = ageMax.trim() === "" ? null : parseAge(ageMax);
  if (ageMin.trim() !== "" && min === null) errors.ageMin = AGE_RANGE_MESSAGE;
  if (ageMax.trim() !== "" && max === null) errors.ageMax = AGE_RANGE_MESSAGE;
  if (min !== null && max !== null && min > max)
    errors.ageMax = AGE_ORDER_MESSAGE;
  return errors;
}
