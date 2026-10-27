import { describe, expect, it } from "vitest";
import {
  groupByDay,
  parseStatusParam,
  parseWeekParam,
  serializeStatus,
  weekLabel,
  weekRange,
} from "../agenda";
import type { Consultation } from "@/services/consultationService";

const c = (id: number, scheduled_at: string): Consultation => ({
  id,
  patient_id: 1,
  scheduled_at,
  duration_minutes: 30,
  status: "scheduled",
  professional_name: "Dra. Ana",
  professional_crm: null,
  reason: null,
  notes: null,
  eye_exam: null,
  created_at: "",
  updated_at: "",
});

describe("agenda week math", () => {
  it("?semana: any day -> Monday; invalid/missing -> current week", () => {
    const today = "2026-10-29"; // Thursday
    expect(parseWeekParam("2026-11-04", today)).toBe("2026-11-02");
    expect(parseWeekParam("2026-11-02", today)).toBe("2026-11-02");
    expect(parseWeekParam(["2026-11-08"], today)).toBe("2026-11-02");
    expect(parseWeekParam("2026-02-30", today)).toBe("2026-10-26");
    expect(parseWeekParam("abc", today)).toBe("2026-10-26");
    expect(parseWeekParam(undefined, today)).toBe("2026-10-26");
    expect(parseWeekParam(null, today)).toBe("2026-10-26");
  });

  it("range is Monday..Sunday (7 days, inside the API's 31-day limit)", () => {
    expect(weekRange("2026-12-28")).toEqual({
      date_from: "2026-12-28",
      date_to: "2027-01-03",
    });
    expect(weekLabel("2026-10-26")).toBe("26/10 a 01/11/2026");
  });

  it("parses and serializes status filters in canonical order", () => {
    expect(parseStatusParam("cancelled,scheduled,bogus,scheduled")).toEqual([
      "scheduled",
      "cancelled",
    ]);
    expect(parseStatusParam(undefined)).toEqual([]);
    expect(serializeStatus(["no_show", "confirmed"])).toBe("confirmed,no_show");
  });

  it("groups by São Paulo day, sorted by time, all 7 days present", () => {
    const days = groupByDay(
      [
        c(1, "2026-10-27T15:00:00-03:00"),
        c(2, "2026-10-27T08:00:00-03:00"),
        // 01:30 UTC on the 2nd = 22:30 on Sunday Nov 1 in São Paulo.
        c(3, "2026-11-02T01:30:00Z"),
        c(4, "2026-11-02T09:00:00-03:00"), // next week: ignored
      ],
      "2026-10-26",
      "2026-10-27"
    );
    expect(days).toHaveLength(7);
    expect(days[1].isToday).toBe(true);
    expect(days[0].isToday).toBe(false);
    expect(days[1].items.map((i) => i.id)).toEqual([2, 1]);
    expect(days[6].items.map((i) => i.id)).toEqual([3]);
    expect(days[0].items).toEqual([]);
  });
});
