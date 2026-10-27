/**
 * Weekly agenda (Mon–Sun, São Paulo days) and its query string:
 * `?semana=YYYY-MM-DD` (Monday of the week; any day is accepted and
 * normalized), `?status=scheduled,confirmed`, `?profissional=texto`.
 */
import type {
  Consultation,
  ConsultationStatus,
} from "@/services/consultationService";
import { isConsultationStatus, STATUS_ORDER } from "./consultationStatus";
import {
  addDays,
  datePart,
  formatDate,
  formatShortDate,
  isValidIsoDate,
  splitDateTime,
  startOfWeek,
  todayInSaoPaulo,
  weekDates,
} from "./datetime";

type QueryValue = string | (string | null)[] | null | undefined;

function first(value: QueryValue): string {
  const v = Array.isArray(value) ? value[0] : value;
  return typeof v === "string" ? v : "";
}

/** Monday of the requested week; invalid/missing -> the current week. */
export function parseWeekParam(
  value: QueryValue,
  today: string = todayInSaoPaulo()
): string {
  const text = first(value);
  return startOfWeek(isValidIsoDate(text) ? text : today);
}

/** Valid statuses from "a,b" (unknown ones dropped), in the canonical order. */
export function parseStatusParam(value: QueryValue): ConsultationStatus[] {
  const wanted = new Set(
    first(value)
      .split(",")
      .map((s) => s.trim())
      .filter(isConsultationStatus)
  );
  return STATUS_ORDER.filter((s) => wanted.has(s));
}

export function serializeStatus(statuses: ConsultationStatus[]): string {
  return STATUS_ORDER.filter((s) => statuses.includes(s)).join(",");
}

/** API range of a week: Monday … Sunday (7 days, within the 31-day limit). */
export function weekRange(monday: string): {
  date_from: string;
  date_to: string;
} {
  return { date_from: monday, date_to: addDays(monday, 6) };
}

/** "26/10 a 01/11/2026". */
export function weekLabel(monday: string): string {
  const sunday = addDays(monday, 6);
  return `${formatShortDate(monday)} a ${formatDate(sunday)}`;
}

export interface AgendaDay {
  date: string;
  isToday: boolean;
  items: Consultation[];
}

/** The 7 days of the week, each with its consultations (time ascending). */
export function groupByDay(
  consultations: Consultation[],
  monday: string,
  today: string = todayInSaoPaulo()
): AgendaDay[] {
  const days = weekDates(monday).map((date) => ({
    date,
    isToday: date === today,
    items: [] as Consultation[],
  }));
  const byDate = new Map(days.map((day) => [day.date, day]));
  consultations.forEach((c) =>
    byDate.get(datePart(c.scheduled_at))?.items.push(c)
  );
  days.forEach((day) =>
    day.items.sort((a, b) =>
      datePartTime(a.scheduled_at).localeCompare(datePartTime(b.scheduled_at))
    )
  );
  return days;
}

/** Sortable "YYYY-MM-DD HH:mm" (São Paulo) of an API datetime. */
function datePartTime(value: string): string {
  const { date, time } = splitDateTime(value);
  return `${date} ${time}`;
}
