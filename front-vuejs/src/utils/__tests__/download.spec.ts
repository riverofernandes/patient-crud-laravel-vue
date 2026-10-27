// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  exportFilename,
  filenameFromContentDisposition,
  saveBlob,
} from "../download";

describe("filenameFromContentDisposition", () => {
  it("reads quoted, unquoted and RFC 5987 names", () => {
    expect(
      filenameFromContentDisposition(
        'attachment; filename="pacientes-20261026-1530.csv"'
      )
    ).toBe("pacientes-20261026-1530.csv");
    expect(filenameFromContentDisposition("attachment; filename=a.csv")).toBe(
      "a.csv"
    );
    expect(
      filenameFromContentDisposition(
        "attachment; filename=\"fallback.csv\"; filename*=UTF-8''pacientes%20exclu%C3%ADdos.csv"
      )
    ).toBe("pacientes excluídos.csv");
  });

  it("returns '' when missing and strips path separators", () => {
    expect(filenameFromContentDisposition(undefined)).toBe("");
    expect(filenameFromContentDisposition("attachment")).toBe("");
    expect(
      filenameFromContentDisposition('attachment; filename="../x/y.csv"')
    ).toBe(".._x_y.csv");
  });
});

describe("exportFilename", () => {
  it("follows pacientes-AAAAMMDD-HHMM.csv in local time", () => {
    expect(exportFilename(new Date(2026, 0, 5, 9, 7))).toBe(
      "pacientes-20260105-0907.csv"
    );
  });
});

describe("saveBlob", () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it("clicks a temporary download link and revokes the URL later", () => {
    vi.useFakeTimers();
    const create = vi.fn(() => "blob:fake");
    const revoke = vi.fn();
    Object.assign(URL, { createObjectURL: create, revokeObjectURL: revoke });
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, "click")
      .mockImplementation(function (this: HTMLAnchorElement) {
        expect(this.download).toBe("x.csv");
        expect(this.getAttribute("href")).toBe("blob:fake");
      });

    saveBlob(new Blob(["a"]), "x.csv");
    expect(click).toHaveBeenCalledOnce();
    expect(document.querySelector("a[download]")).toBeNull();
    expect(revoke).not.toHaveBeenCalled();
    vi.runAllTimers();
    expect(revoke).toHaveBeenCalledWith("blob:fake");
    click.mockRestore();
  });
});
