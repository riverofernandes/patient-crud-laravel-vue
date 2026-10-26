import { beforeEach, describe, expect, it, vi } from "vitest";
import { AxiosError, AxiosHeaders } from "axios";
import { api, getErrorMessage } from "../api";
import { patientService } from "../patientService";

vi.mock("@/utils/download", async () => ({
  ...(await vi.importActual<object>("@/utils/download")),
  exportFilename: () => "pacientes-local.csv",
}));

const get = vi.spyOn(api, "get");

beforeEach(() => {
  get.mockReset();
});

describe("patientService", () => {
  it("list sends filters only when set (age 0 included)", async () => {
    get.mockResolvedValue({ data: { data: [], meta: {} } });
    await patientService.list({ sex: "F", age_min: 0, search: "" });
    expect(get).toHaveBeenCalledWith("/patients", {
      params: { page: 1, per_page: 10, sex: "F", age_min: 0 },
    });
  });

  it("exportCsv requests a blob with the filters and uses Content-Disposition", async () => {
    const blob = new Blob(["x"]);
    get.mockResolvedValue({
      data: blob,
      headers: {
        "content-disposition": 'attachment; filename="pacientes-1.csv"',
      },
    });
    const result = await patientService.exportCsv({
      search: "Ana",
      sort: "name",
      direction: "desc",
      age_max: 60,
      trashed: "only",
    });
    expect(result).toEqual({ blob, filename: "pacientes-1.csv" });
    expect(get).toHaveBeenCalledWith(
      "/patients/export",
      expect.objectContaining({
        params: {
          search: "Ana",
          sort: "name",
          direction: "desc",
          age_max: 60,
          trashed: "only",
        },
        responseType: "blob",
      })
    );
  });

  it("exportCsv falls back to a local filename when the header is not exposed", async () => {
    get.mockResolvedValue({ data: new Blob(["x"]), headers: {} });
    const { filename } = await patientService.exportCsv();
    expect(filename).toBe("pacientes-local.csv");
  });

  it("exportCsv turns a Blob error body back into JSON", async () => {
    const response = {
      status: 422,
      statusText: "",
      headers: {},
      config: { headers: new AxiosHeaders() },
      data: new Blob([JSON.stringify({ message: "Filtro inválido." })]),
    };
    const failure = new AxiosError(
      "Request failed",
      "ERR_BAD_REQUEST",
      undefined,
      undefined,
      response as never
    );
    // A plain function (not the spy): tinyspy 1 tracks returned promises in a
    // way that reports this expected rejection as unhandled.
    const spied = api.get;
    api.get = (() => Promise.reject(failure)) as never;
    let error: unknown;
    try {
      await patientService.exportCsv();
    } catch (e) {
      error = e;
    } finally {
      api.get = spied;
    }
    expect(getErrorMessage(error)).toBe("Filtro inválido.");
  });

  it("audits paginates", async () => {
    get.mockResolvedValue({ data: { data: [], meta: {} } });
    await patientService.audits(7, { page: 2 });
    expect(get).toHaveBeenCalledWith("/patients/7/audits", {
      params: { page: 2, per_page: 10 },
    });
  });
});
