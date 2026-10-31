import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { debounce } from "../debounce";

beforeEach(() => {
  vi.useFakeTimers();
});
afterEach(() => {
  vi.useRealTimers();
});

describe("debounce", () => {
  it("calls once with the last arguments after the delay", () => {
    const fn = vi.fn();
    const d = debounce(fn, 400);
    d("a");
    d("ab");
    vi.advanceTimersByTime(399);
    expect(fn).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(fn).toHaveBeenCalledTimes(1);
    expect(fn).toHaveBeenCalledWith("ab");
  });

  it("pending() is true only while a call waits", () => {
    const d = debounce(vi.fn(), 400);
    expect(d.pending()).toBe(false);
    d();
    expect(d.pending()).toBe(true);
    vi.advanceTimersByTime(400);
    expect(d.pending()).toBe(false);
  });

  it("cancel() drops the pending call", () => {
    const fn = vi.fn();
    const d = debounce(fn, 400);
    d();
    d.cancel();
    expect(d.pending()).toBe(false);
    vi.advanceTimersByTime(1000);
    expect(fn).not.toHaveBeenCalled();
  });
});
