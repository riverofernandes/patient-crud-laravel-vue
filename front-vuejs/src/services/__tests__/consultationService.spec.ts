import { beforeEach, describe, expect, it, vi } from "vitest";
import { AxiosError, AxiosHeaders } from "axios";
import { api, getErrorMessage } from "../api";
import {
  consultationService,
  prescriptionFilename,
} from "../consultationService";

const get = vi.spyOn(api, "get");
const post = vi.spyOn(api, "post");
const put = vi.spyOn(api, "put");
const patch = vi.spyOn(api, "patch");

beforeEach(() => {
  [get, post, put, patch].forEach((m) => m.mockReset());
});

describe("consultationService", () => {
  it("lists a patient's consultations and timeline with pagination", async () => {
    get.mockResolvedValue({ data: { data: [], meta: {} } });
    await consultationService.listByPatient(7, { page: 2 });
    expect(get).toHaveBeenCalledWith("/patients/7/consultations", {
      params: { page: 2, per_page: 10 },
    });
    await consultationService.timeline(7);
    expect(get).toHaveBeenLastCalledWith("/patients/7/timeline", {
      params: { page: 1, per_page: 10 },
    });
  });

  it("agenda sends the range and only set filters", async () => {
    get.mockResolvedValue({ data: { data: [{ id: 1 }] } });
    const result = await consultationService.agenda({
      date_from: "2026-10-26",
      date_to: "2026-11-01",
      status: ["scheduled", "confirmed"],
      professional: "  Ana ",
    });
    expect(result).toEqual([{ id: 1 }]);
    expect(get).toHaveBeenCalledWith("/consultations", {
      params: {
        date_from: "2026-10-26",
        date_to: "2026-11-01",
        status: "scheduled,confirmed",
        professional: "Ana",
      },
    });
    await consultationService.agenda({
      date_from: "2026-10-26",
      date_to: "2026-11-01",
      status: [],
      professional: " ",
    });
    expect(get).toHaveBeenLastCalledWith("/consultations", {
      params: { date_from: "2026-10-26", date_to: "2026-11-01" },
    });
  });

  it("CRUD, status and eye exam unwrap { data }", async () => {
    const payload = {
      scheduled_at: "2026-11-03T14:30",
      duration_minutes: 30,
      professional_name: "Dra. Ana",
      professional_crm: null,
      reason: null,
      notes: null,
    };
    post.mockResolvedValue({ data: { data: { id: 1 } } });
    expect(await consultationService.create(7, payload)).toEqual({ id: 1 });
    expect(post).toHaveBeenCalledWith("/patients/7/consultations", payload);

    get.mockResolvedValue({ data: { data: { id: 2 } } });
    expect(await consultationService.get(2)).toEqual({ id: 2 });

    put.mockResolvedValue({ data: { data: { id: 2 } } });
    await consultationService.update(2, payload);
    expect(put).toHaveBeenCalledWith("/consultations/2", payload);

    patch.mockResolvedValue({ data: { data: { id: 2, status: "confirmed" } } });
    await consultationService.changeStatus(2, "confirmed");
    expect(patch).toHaveBeenCalledWith("/consultations/2/status", {
      status: "confirmed",
    });

    const exam = { od: {}, oe: {}, diagnosis: null, observations: null };
    await consultationService.saveEyeExam(2, exam as never);
    expect(put).toHaveBeenLastCalledWith("/consultations/2/eye-exam", exam);
  });

  it("prescription uses Content-Disposition, else a local name", async () => {
    const blob = new Blob(["%PDF"]);
    get.mockResolvedValueOnce({
      data: blob,
      headers: {
        "content-disposition": 'attachment; filename="receita-2-20261103.pdf"',
      },
    });
    expect(await consultationService.downloadPrescription(2)).toEqual({
      blob,
      filename: "receita-2-20261103.pdf",
    });
    expect(get).toHaveBeenCalledWith("/consultations/2/prescription", {
      responseType: "blob",
      headers: { Accept: "application/pdf, application/json" },
      timeout: 60000,
    });
    get.mockResolvedValueOnce({ data: blob, headers: {} });
    expect(
      (await consultationService.downloadPrescription(2, "2026-11-03")).filename
    ).toBe("receita-2-20261103.pdf");
    expect(prescriptionFilename(5)).toBe("receita-5.pdf");
  });

  it("prescription 422 Blob body is readable by getErrorMessage", async () => {
    const body = new Blob([
      JSON.stringify({ message: "O prontuário não tem refração." }),
    ]);
    const error = new AxiosError("422", "ERR", undefined, undefined, {
      status: 422,
      data: body,
      statusText: "",
      headers: {},
      config: { headers: new AxiosHeaders() },
    });
    get.mockRejectedValue(error);
    const caught = await consultationService
      .downloadPrescription(2)
      .catch((e) => e);
    expect(getErrorMessage(caught)).toBe("O prontuário não tem refração.");
  });
});
