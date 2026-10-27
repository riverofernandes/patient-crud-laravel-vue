import { describe, expect, it } from "vitest";
import { groupTimelineByDay, mergeTimeline, timelineKey } from "../timeline";
import type { TimelineItem } from "@/services/consultationService";

const consultation = (id: number, at: string): TimelineItem => ({
  type: "consultation",
  occurred_at: at,
  consultation: {
    id,
    patient_id: 1,
    scheduled_at: at,
    duration_minutes: 30,
    status: "completed",
    professional_name: "Dr. X",
    professional_crm: null,
    reason: null,
    notes: null,
    eye_exam: null,
    created_at: "",
    updated_at: "",
  },
});
const audit = (id: number, at: string): TimelineItem => ({
  type: "audit",
  occurred_at: at,
  audit: { id, event: "updated", changes: {}, created_at: at },
});

describe("timeline", () => {
  it("keys by type (ids can collide)", () => {
    expect(timelineKey(consultation(5, "2026-11-03T10:00:00-03:00"))).toBe(
      "consultation-5"
    );
    expect(timelineKey(audit(5, "2026-11-03T10:00:00-03:00"))).toBe("audit-5");
  });

  it("merges pages without duplicates", () => {
    const a = consultation(1, "2026-11-03T10:00:00-03:00");
    const b = audit(1, "2026-11-03T09:00:00-03:00");
    const c = audit(2, "2026-11-02T09:00:00-03:00");
    expect(mergeTimeline([a, b], [b, c]).map(timelineKey)).toEqual([
      "consultation-1",
      "audit-1",
      "audit-2",
    ]);
  });

  it("groups by São Paulo day keeping order; a day split across pages stays one group", () => {
    const items = [
      consultation(3, "2026-11-03T14:30:00-03:00"),
      audit(9, "2026-11-03T10:00:00-03:00"),
      // 01:00 UTC on the 3rd is still the 2nd in São Paulo.
      audit(8, "2026-11-03T01:00:00Z"),
      consultation(2, "2026-10-27T09:00:00-03:00"),
    ];
    const page2 = [audit(7, "2026-10-27T08:00:00-03:00")];
    const groups = groupTimelineByDay(
      mergeTimeline(items, page2),
      "2026-11-03"
    );
    expect(groups.map((g) => g.date)).toEqual([
      "2026-11-03",
      "2026-11-02",
      "2026-10-27",
    ]);
    expect(groups[0].label).toBe("Hoje, Terça-feira, 03/11/2026");
    expect(groups[1].label).toBe("Segunda-feira, 02/11/2026");
    expect(groups[0].items.map(timelineKey)).toEqual([
      "consultation-3",
      "audit-9",
    ]);
    expect(groups[2].items.map(timelineKey)).toEqual([
      "consultation-2",
      "audit-7",
    ]);
  });

  it("puts unparseable dates in a fallback group", () => {
    const groups = groupTimelineByDay([audit(1, "bad")], "2026-11-03");
    expect(groups[0].label).toBe("Data desconhecida");
  });
});
