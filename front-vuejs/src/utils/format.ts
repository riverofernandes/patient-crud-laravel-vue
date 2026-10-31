/**
 * Formatting / masking helpers.
 *
 * Dates are always handled as plain strings (YYYY-MM-DD <-> DD/MM/AAAA) to
 * avoid timezone shifts caused by `new Date('YYYY-MM-DD')` (parsed as UTC).
 */
import { splitDateTime, todayInSaoPaulo } from "./datetime";

export function onlyDigits(value: string | null | undefined): string {
  return (value ?? "").replace(/\D/g, "");
}

/** 00000000000 -> 000.000.000-00 (progressive, works while typing). */
export function maskCpf(value: string | null | undefined): string {
  const d = onlyDigits(value).slice(0, 11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
  if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
}

/** Keeps digits and the letter X (RG check digit), uppercased. */
export function normalizeRg(value: string | null | undefined): string {
  return (value ?? "")
    .toUpperCase()
    .replace(/[^0-9X]/g, "")
    .slice(0, 20);
}

/**
 * RG mask in the common SP format 00.000.000-0 (check digit may be X).
 * RGs from other states have different lengths, so anything longer than
 * 9 characters is shown unformatted.
 */
export function maskRg(value: string | null | undefined): string {
  const v = normalizeRg(value);
  if (v.length > 9) return v;
  if (v.length <= 2) return v;
  if (v.length <= 5) return `${v.slice(0, 2)}.${v.slice(2)}`;
  if (v.length <= 8) return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5)}`;
  return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}-${v.slice(8)}`;
}

/** (00) 0000-0000 for landlines, (00) 00000-0000 for mobiles. */
export function maskPhone(value: string | null | undefined): string {
  const d = onlyDigits(value).slice(0, 11);
  if (d.length === 0) return "";
  if (d.length <= 2) return `(${d}`;
  const ddd = d.slice(0, 2);
  const rest = d.slice(2);
  if (rest.length <= 4) return `(${ddd}) ${rest}`;
  if (d.length <= 10) return `(${ddd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
  return `(${ddd}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
}

/** DD/MM/AAAA progressive mask. */
export function maskDate(value: string | null | undefined): string {
  const d = onlyDigits(value).slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;
  return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
}

/** YYYY-MM-DD (or ISO datetime) -> DD/MM/AAAA. Returns '' for invalid input. */
export function isoToBr(value: string | null | undefined): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? "");
  if (!match) return "";
  const [, y, m, d] = match;
  return `${d}/${m}/${y}`;
}

/** DD/MM/AAAA -> YYYY-MM-DD. Returns '' when incomplete. */
export function brToIso(value: string | null | undefined): string {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value ?? "");
  if (!match) return "";
  const [, d, m, y] = match;
  return `${y}-${m}-${d}`;
}

/** Checks that DD/MM/AAAA is a real calendar date (no Date parsing of strings). */
export function isValidBrDate(value: string | null | undefined): boolean {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value ?? "");
  if (!match) return false;
  const day = Number(match[1]);
  const month = Number(match[2]);
  const year = Number(match[3]);
  if (year < 1900 || month < 1 || month > 12 || day < 1) return false;
  const daysInMonth = new Date(year, month, 0).getDate();
  return day <= daysInMonth;
}

/** True when the DD/MM/AAAA date is after today (São Paulo). */
export function isFutureBrDate(value: string | null | undefined): boolean {
  const iso = brToIso(value);
  return iso !== "" && iso > todayIso();
}

/**
 * Today's date in São Paulo as YYYY-MM-DD (the clinic's calendar, whatever
 * the browser's time zone; same day the API uses for "future" checks).
 */
export function todayIso(): string {
  return todayInSaoPaulo();
}

export const SEX_LABELS: Record<string, string> = {
  M: "Masculino",
  F: "Feminino",
};

export function formatSex(value: string | null | undefined): string {
  return value ? SEX_LABELS[value] ?? value : "";
}

/** Full years between a YYYY-MM-DD birth date and today (São Paulo), by string math. */
export function ageFromBirthDate(
  birthDate: string | null | undefined,
  today: string = todayIso()
): number | null {
  const b = /^(\d{4})-(\d{2})-(\d{2})/.exec(birthDate ?? "");
  const t = /^(\d{4})-(\d{2})-(\d{2})/.exec(today);
  if (!b || !t) return null;
  let age = Number(t[1]) - Number(b[1]);
  if (`${t[2]}${t[3]}` < `${b[2]}${b[3]}`) age -= 1;
  return age >= 0 ? age : null;
}

/** 0 -> "Menos de 1 ano", 1 -> "1 ano", N -> "N anos". */
export function formatAge(age: number | null | undefined): string {
  if (age === null || age === undefined || !Number.isFinite(age) || age < 0)
    return "";
  if (age === 0) return "Menos de 1 ano";
  return age === 1 ? "1 ano" : `${age} anos`;
}

/** Patient age: the API's `age` when present, otherwise computed from birth_date. */
export function patientAge(patient: {
  age?: number | null;
  birth_date?: string | null;
}): string {
  return formatAge(patient.age ?? ageFromBirthDate(patient.birth_date));
}

/**
 * ISO-8601 datetime -> DD/MM/AAAA HH:mm in America/Sao_Paulo (UTC−03:00, no
 * DST), whatever the browser's time zone. Same wall clock as the new screens.
 */
export function formatDateTime(value: string | null | undefined): string {
  const { date, time } = splitDateTime(value);
  if (!date) return "";
  const [y, m, d] = date.split("-");
  return `${d}/${m}/${y} ${time}`;
}
