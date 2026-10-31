import { beforeEach, describe, expect, it, vi } from "vitest";
import { AxiosError, AxiosHeaders } from "axios";
import {
  PATIENT_RESTORED_EVENT,
  restorePatient,
  restoreSuccessMessage,
} from "../restore";
import { patientService } from "@/services/patientService";
import { notify } from "../notify";

vi.mock("@/services/patientService", () => ({
  patientService: { restore: vi.fn() },
}));
vi.mock("../notify", () => ({ notify: vi.fn() }));

const root = { $emit: vi.fn() };
const patient = { id: 3, name: "Ana Souza" };

const httpError = (status: number, message?: string) =>
  new AxiosError("Request failed", "ERR_BAD_REQUEST", undefined, undefined, {
    status,
    statusText: "",
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: message ? { message } : {},
  } as never);

beforeEach(() => {
  vi.mocked(notify).mockReset();
  root.$emit.mockReset();
});

describe("restoreSuccessMessage", () => {
  it("keeps the plain message when nothing was cancelled", () => {
    expect(restoreSuccessMessage(0)).toBe("Paciente restaurado com sucesso!");
  });

  it("mentions the cancelled future consultations (singular/plural)", () => {
    expect(restoreSuccessMessage(1)).toBe(
      "Paciente restaurado com sucesso! 1 atendimento futuro foi cancelado por conflito de horário."
    );
    expect(restoreSuccessMessage(2)).toBe(
      "Paciente restaurado com sucesso! 2 atendimentos futuros foram cancelados por conflito de horário."
    );
  });
});

describe("restorePatient", () => {
  it("toasts the cancellations and emits the restored patient", async () => {
    vi.mocked(patientService.restore).mockResolvedValue({
      patient: { id: 3 } as never,
      cancelledConsultations: [
        { id: 1, scheduled_at: "", professional_name: "" },
        { id: 2, scheduled_at: "", professional_name: "" },
      ],
    });
    expect(await restorePatient(root, patient)).toBe("restored");
    expect(notify).toHaveBeenCalledWith(
      root,
      "success",
      restoreSuccessMessage(2)
    );
    expect(root.$emit).toHaveBeenCalledWith(PATIENT_RESTORED_EVENT, { id: 3 });
  });

  it("reports a patient no longer in the trash (404)", async () => {
    vi.mocked(patientService.restore).mockRejectedValue(httpError(404));
    expect(await restorePatient(root, patient)).toBe("not-found");
    expect(notify).toHaveBeenCalledWith(
      root,
      "error",
      "Este paciente não está mais na lixeira."
    );
  });

  it("shows the API message on other errors (409 CPF in use)", async () => {
    vi.mocked(patientService.restore).mockRejectedValue(
      httpError(409, "Já existe um paciente ativo com este CPF.")
    );
    expect(await restorePatient(root, patient)).toBe("failed");
    expect(notify).toHaveBeenLastCalledWith(
      root,
      "error",
      "Já existe um paciente ativo com este CPF."
    );
    expect(root.$emit).not.toHaveBeenCalled();
  });
});
