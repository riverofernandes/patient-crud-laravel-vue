import { afterEach, describe, expect, it, vi } from "vitest";
import { ageFromBirthDate, brToIso, isFutureBrDate, todayIso } from "../format";

const originalTz = process.env.TZ;

afterEach(() => {
  vi.useRealTimers();
  process.env.TZ = originalTz;
});

describe("todayIso (São Paulo calendar)", () => {
  // 02:00 UTC on Oct 30 = 23:00 on Oct 29 in São Paulo (UTC−03:00).
  const instant = new Date("2026-10-30T02:00:00Z");

  it.each(["UTC", "Asia/Tokyo", "Pacific/Honolulu", "America/Sao_Paulo"])(
    "is the São Paulo date whatever the browser zone (%s)",
    (zone) => {
      process.env.TZ = zone;
      vi.useFakeTimers();
      vi.setSystemTime(instant);
      expect(todayIso()).toBe("2026-10-29");
    }
  );

  it("drives the future-date check and the age fallback", () => {
    process.env.TZ = "Asia/Tokyo"; // already Oct 30 locally
    vi.useFakeTimers();
    vi.setSystemTime(instant);
    expect(isFutureBrDate("30/10/2026")).toBe(true);
    expect(isFutureBrDate("29/10/2026")).toBe(false);
    // Birthday is "tomorrow" in São Paulo: not 30 yet.
    expect(ageFromBirthDate(brToIso("30/10/1996"))).toBe(29);
  });
});
