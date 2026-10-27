import { describe, expect, it } from "vitest";
import {
  addDays,
  addMinutesToTime,
  datePart,
  formatDateTimeSp,
  formatDayHeading,
  isValidIsoDate,
  saoPauloParts,
  splitDateTime,
  startOfWeek,
  timePart,
  timeRange,
  todayInSaoPaulo,
  weekDates,
  weekdayIndex,
} from "../datetime";

describe("datetime (America/Sao_Paulo, no DST)", () => {
  it("today in São Paulo does not depend on the browser zone", () => {
    // 02:30 UTC on Nov 3 is still Nov 2 in São Paulo (UTC−3).
    expect(todayInSaoPaulo(new Date("2026-11-03T02:30:00Z"))).toBe(
      "2026-11-02"
    );
    expect(todayInSaoPaulo(new Date("2026-11-03T03:00:00Z"))).toBe(
      "2026-11-03"
    );
    // October/February used to be DST: still −03:00 since 2019.
    expect(saoPauloParts(new Date("2026-10-18T12:00:00Z"))).toEqual({
      date: "2026-10-18",
      time: "09:00",
    });
    expect(saoPauloParts(new Date("2027-02-14T02:59:00Z")).date).toBe(
      "2027-02-13"
    );
  });

  it("reads API datetimes as São Paulo wall clock", () => {
    expect(splitDateTime("2026-11-03T14:30:00-03:00")).toEqual({
      date: "2026-11-03",
      time: "14:30",
    });
    expect(splitDateTime("2026-11-03T14:30")).toEqual({
      date: "2026-11-03",
      time: "14:30",
    });
    // Other offsets are converted.
    expect(splitDateTime("2026-11-04T01:15:00Z")).toEqual({
      date: "2026-11-03",
      time: "22:15",
    });
    expect(splitDateTime("2026-11-03T20:00:00+02:00").time).toBe("15:00");
    expect(splitDateTime("garbage")).toEqual({ date: "", time: "" });
    expect(splitDateTime(null)).toEqual({ date: "", time: "" });
    expect(datePart("2026-01-01T00:10:00-03:00")).toBe("2026-01-01");
    expect(timePart("2026-01-01T00:10:00.000-03:00")).toBe("00:10");
  });

  it("validates calendar dates without Date parsing of strings", () => {
    expect(isValidIsoDate("2026-02-28")).toBe(true);
    expect(isValidIsoDate("2028-02-29")).toBe(true);
    expect(isValidIsoDate("2026-02-29")).toBe(false);
    expect(isValidIsoDate("2026-13-01")).toBe(false);
    expect(isValidIsoDate("26-01-01")).toBe(false);
    expect(isValidIsoDate(undefined)).toBe(false);
  });

  it("does day arithmetic across months, years and old DST dates", () => {
    expect(addDays("2026-10-31", 1)).toBe("2026-11-01");
    expect(addDays("2026-12-28", 7)).toBe("2027-01-04");
    expect(addDays("2026-03-01", -1)).toBe("2026-02-28");
    // Former DST start (1st Sunday of November until 2018).
    expect(addDays("2026-10-31", 2)).toBe("2026-11-02");
  });

  it("computes Monday-based weeks", () => {
    expect(weekdayIndex("2026-10-26")).toBe(0); // Monday
    expect(weekdayIndex("2026-11-01")).toBe(6); // Sunday
    expect(startOfWeek("2026-11-01")).toBe("2026-10-26");
    expect(startOfWeek("2026-10-26")).toBe("2026-10-26");
    expect(startOfWeek("2027-01-01")).toBe("2026-12-28");
    expect(weekDates("2026-10-26")).toEqual([
      "2026-10-26",
      "2026-10-27",
      "2026-10-28",
      "2026-10-29",
      "2026-10-30",
      "2026-10-31",
      "2026-11-01",
    ]);
  });

  it("formats times, ranges and headings", () => {
    expect(addMinutesToTime("14:30", 30)).toBe("15:00");
    expect(addMinutesToTime("23:45", 30)).toBe("00:15");
    expect(addMinutesToTime("bad", 30)).toBe("");
    expect(timeRange("2026-11-03T14:30:00-03:00", 45)).toBe("14:30–15:15");
    expect(timeRange("", 45)).toBe("");
    expect(formatDayHeading("2026-11-03")).toBe("Terça-feira, 03/11/2026");
    expect(formatDayHeading("nope")).toBe("");
    expect(formatDateTimeSp("2026-11-03T14:30:00-03:00")).toBe(
      "03/11/2026 às 14:30"
    );
    expect(formatDateTimeSp(null)).toBe("");
  });
});
