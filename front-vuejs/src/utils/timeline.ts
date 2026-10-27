/**
 * Patient timeline (GET /patients/{id}/timeline): stable keys, de-duplication
 * across pages and grouping by São Paulo day.
 */
import type { TimelineItem } from "@/services/consultationService";
import { datePart, formatDayHeading, todayInSaoPaulo } from "./datetime";

/** Consultation and audit ids can collide: key by type too. */
export function timelineKey(item: TimelineItem): string {
  return item.type === "consultation"
    ? `consultation-${item.consultation.id}`
    : `audit-${item.audit.id}`;
}

/** Appends a new page, skipping items already shown (pages can shift). */
export function mergeTimeline(
  current: TimelineItem[],
  fresh: TimelineItem[]
): TimelineItem[] {
  const seen = new Set(current.map(timelineKey));
  return [...current, ...fresh.filter((item) => !seen.has(timelineKey(item)))];
}

export interface TimelineDay {
  date: string;
  label: string;
  items: TimelineItem[];
}

/**
 * Groups items (already newest first) by day, keeping the API order. A day
 * split across two pages ends up in a single group.
 */
export function groupTimelineByDay(
  items: TimelineItem[],
  today: string = todayInSaoPaulo()
): TimelineDay[] {
  const groups: TimelineDay[] = [];
  const byDate = new Map<string, TimelineDay>();
  items.forEach((item) => {
    const date = datePart(item.occurred_at);
    let group = byDate.get(date);
    if (!group) {
      const heading = formatDayHeading(date) || "Data desconhecida";
      group = {
        date,
        label: date === today ? `Hoje, ${heading}` : heading,
        items: [],
      };
      byDate.set(date, group);
      groups.push(group);
    }
    group.items.push(item);
  });
  return groups;
}
