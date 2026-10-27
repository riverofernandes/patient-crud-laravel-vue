/**
 * Calendar helpers in America/Sao_Paulo semantics.
 *
 * Brazil has had no daylight saving time since 2019, so São Paulo is always
 * UTC−03:00. Dates are handled as `YYYY-MM-DD` strings and day arithmetic is
 * done on UTC timestamps (`Date.UTC` + `getUTC*`), so nothing depends on the
 * browser's own time zone (never `new Date('YYYY-MM-DD')`).
 */

export const SAO_PAULO_TZ = "America/Sao_Paulo";
const SAO_PAULO_OFFSET_MINUTES = -180;
const DAY_MS = 24 * 60 * 60 * 1000;

export const WEEKDAY_NAMES = [
  "Segunda-feira",
  "Terça-feira",
  "Quarta-feira",
  "Quinta-feira",
  "Sexta-feira",
  "Sábado",
  "Domingo",
] as const;

const pad = (n: number) => String(n).padStart(2, "0");

/** YYYY-MM-DD of a UTC timestamp's calendar fields. */
function utcToIsoDate(ms: number): string {
  const d = new Date(ms);
  return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(
    d.getUTCDate()
  )}`;
}

function isoDateToUtc(value: string): number {
  const [y, m, d] = value.split("-").map(Number);
  return Date.UTC(y, m - 1, d);
}

/** Whether a YYYY-MM-DD string is a real calendar date. */
export function isValidIsoDate(value: string | null | undefined): boolean {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? "");
  if (!match) return false;
  const [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])];
  if (y < 1900 || m < 1 || m > 12 || d < 1) return false;
  return utcToIsoDate(Date.UTC(y, m - 1, d)) === value;
}

/** Wall-clock date and time in São Paulo of a moment. */
export function saoPauloParts(now: Date = new Date()): {
  date: string;
  time: string;
} {
  const shifted = now.getTime() + SAO_PAULO_OFFSET_MINUTES * 60 * 1000;
  const d = new Date(shifted);
  return {
    date: utcToIsoDate(shifted),
    time: `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`,
  };
}

/** Today's date in São Paulo (YYYY-MM-DD), whatever the browser's zone. */
export function todayInSaoPaulo(now: Date = new Date()): string {
  return saoPauloParts(now).date;
}

const API_DATETIME =
  /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(?::\d{2}(?:\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/;

/**
 * Splits an API datetime into São Paulo date/time. The API always answers with
 * the −03:00 offset, so the string is read as is; other offsets are converted.
 */
export function splitDateTime(value: string | null | undefined): {
  date: string;
  time: string;
} {
  const match = API_DATETIME.exec(value ?? "");
  if (!match) return { date: "", time: "" };
  const [, date, time, offset] = match;
  if (!offset || offset === "-03:00" || offset === "-0300") {
    return { date, time };
  }
  const ms = Date.parse(value as string);
  return Number.isNaN(ms)
    ? { date: "", time: "" }
    : saoPauloParts(new Date(ms));
}

/** API datetime -> YYYY-MM-DD (São Paulo). */
export function datePart(value: string | null | undefined): string {
  return splitDateTime(value).date;
}

/** API datetime -> HH:mm (São Paulo). */
export function timePart(value: string | null | undefined): string {
  return splitDateTime(value).time;
}

/** HH:mm plus some minutes, wrapping past midnight. */
export function addMinutesToTime(time: string, minutes: number): string {
  const match = /^(\d{2}):(\d{2})$/.exec(time);
  if (!match) return "";
  const total =
    (((Number(match[1]) * 60 + Number(match[2]) + minutes) % 1440) + 1440) %
    1440;
  return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
}

/** "14:30–15:00" for a consultation. */
export function timeRange(
  scheduledAt: string,
  durationMinutes: number
): string {
  const start = timePart(scheduledAt);
  if (!start) return "";
  return `${start}–${addMinutesToTime(start, durationMinutes)}`;
}

/** YYYY-MM-DD plus N days (negative goes back). */
export function addDays(date: string, days: number): string {
  return utcToIsoDate(isoDateToUtc(date) + days * DAY_MS);
}

/** 0 = Monday … 6 = Sunday. */
export function weekdayIndex(date: string): number {
  return (new Date(isoDateToUtc(date)).getUTCDay() + 6) % 7;
}

/** Monday of the week that contains the date. */
export function startOfWeek(date: string): string {
  return addDays(date, -weekdayIndex(date));
}

/** The 7 dates (Mon–Sun) of the week starting on `monday`. */
export function weekDates(monday: string): string[] {
  return Array.from({ length: 7 }, (_, i) => addDays(monday, i));
}

/** YYYY-MM-DD -> DD/MM/AAAA. */
export function formatDate(date: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(date);
  return match ? `${match[3]}/${match[2]}/${match[1]}` : "";
}

/** YYYY-MM-DD -> DD/MM. */
export function formatShortDate(date: string): string {
  return formatDate(date).slice(0, 5);
}

/** "Segunda-feira, 26/10/2026". */
export function formatDayHeading(date: string): string {
  if (!isValidIsoDate(date)) return "";
  return `${WEEKDAY_NAMES[weekdayIndex(date)]}, ${formatDate(date)}`;
}

/** API datetime -> "03/11/2026 às 14:30" (São Paulo). */
export function formatDateTimeSp(value: string | null | undefined): string {
  const { date, time } = splitDateTime(value);
  return date ? `${formatDate(date)} às ${time}` : "";
}
