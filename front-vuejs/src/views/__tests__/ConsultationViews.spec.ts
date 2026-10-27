// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import ConsultationDetailView from "../ConsultationDetailView.vue";
import ConsultationCreateView from "../ConsultationCreateView.vue";
import { consultationService } from "@/services/consultationService";
import { patientService } from "@/services/patientService";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";
import { exam, makeConsultation, validationError } from "@/test/fixtures";

vi.mock("@/services/consultationService", () => ({
  consultationService: {
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    changeStatus: vi.fn(),
    saveEyeExam: vi.fn(),
    downloadPrescription: vi.fn(),
  },
}));
vi.mock("@/services/patientService", () => ({
  patientService: { get: vi.fn() },
}));
vi.mock("@/services/attachmentService", () => ({
  attachmentService: { list: vi.fn().mockResolvedValue([]) },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));
vi.mock("@/utils/download", async () => ({
  ...(await vi.importActual<object>("@/utils/download")),
  saveBlob: vi.fn(),
}));

const svc = vi.mocked(consultationService);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;
let router: VueRouter;

async function mountView(component: unknown, path: string, id: string) {
  router = makeRouter();
  await router.push(path).catch(() => undefined);
  wrapper = mount(component as never, {
    localVue,
    router,
    propsData: { id },
    attachTo: document.body,
  });
  await flushPromises();
  await wrapper.vm.$nextTick();
}
const btn = (name: string) =>
  wrapper.findAll("button").filter((b) => b.text().trim() === name);

beforeEach(() => {
  Object.values(svc).forEach((fn) => vi.mocked(fn).mockReset());
  vi.mocked(notify).mockReset();
  vi.mocked(saveBlob).mockReset();
});
afterEach(() => wrapper?.destroy());

describe("ConsultationDetailView", () => {
  it("shows the summary, breadcrumb and the actions allowed for scheduled", async () => {
    svc.get.mockResolvedValue(makeConsultation());
    await mountView(ConsultationDetailView, "/atendimentos/11", "11");
    expect(wrapper.find("h1").text()).toBe("Atendimento 03/11/2026");
    expect(document.title).toBe("Atendimento 03/11/2026 | EyeCare");
    const crumbs = wrapper
      .findAll(".app-breadcrumb li")
      .wrappers.map((l) => l.text());
    expect(crumbs).toEqual([
      "Pacientes",
      "Ana Souza",
      "Atendimento 03/11/2026",
    ]);
    expect(wrapper.text()).toContain("Terça-feira, 03/11/2026");
    expect(wrapper.text()).toContain("14:30–15:00");
    expect(wrapper.text()).toContain("Dra. Helena Prado");
    expect(wrapper.find("#consultation-status").text()).toContain("Agendado");
    expect(wrapper.findAll(".status-actions__btn")).toHaveLength(4);
    expect(wrapper.find(".prontuario").exists()).toBe(false);
  });

  it("marking as completed reveals the prontuário form", async () => {
    svc.get.mockResolvedValue(makeConsultation({ status: "confirmed" }));
    svc.changeStatus.mockResolvedValue(
      makeConsultation({ status: "completed" })
    );
    await mountView(ConsultationDetailView, "/atendimentos/11", "11");
    await wrapper.find("[data-action='completed']").trigger("click");
    await flushPromises();
    expect(wrapper.find("#consultation-status").text()).toContain("Realizado");
    expect(wrapper.findAll(".status-actions__btn")).toHaveLength(0);
    expect(wrapper.find(".eye-exam-form").exists()).toBe(true);
  });

  it("saved prontuário: read-only view, edit, and PDF download", async () => {
    svc.get.mockResolvedValue(
      makeConsultation({ status: "completed", eye_exam: exam })
    );
    await mountView(ConsultationDetailView, "/atendimentos/11", "11");
    expect(wrapper.find(".eye-table").exists()).toBe(true);
    expect(wrapper.find(".eye-exam-form").exists()).toBe(false);

    const blob = new Blob(["%PDF"]);
    svc.downloadPrescription.mockResolvedValueOnce({
      blob,
      filename: "receita-11-20261103.pdf",
    });
    await btn("Baixar receita (PDF)").at(0).trigger("click");
    await flushPromises();
    expect(svc.downloadPrescription).toHaveBeenCalledWith(11, "2026-11-03");
    expect(saveBlob).toHaveBeenCalledWith(blob, "receita-11-20261103.pdf");

    svc.downloadPrescription.mockRejectedValueOnce(
      Object.assign(new Error("422"), {
        isAxiosError: true,
        response: {
          status: 422,
          data: { message: "Sem refração para a receita." },
        },
      })
    );
    await btn("Baixar receita (PDF)").at(0).trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenLastCalledWith(
      expect.anything(),
      "error",
      "Sem refração para a receita."
    );

    await btn("Editar prontuário").at(0).trigger("click");
    expect(wrapper.find(".eye-exam-form").exists()).toBe(true);
    await btn("Cancelar").at(0).trigger("click");
    expect(wrapper.find(".eye-table").exists()).toBe(true);

    await btn("Editar prontuário").at(0).trigger("click");
    svc.saveEyeExam.mockResolvedValue(
      makeConsultation({
        status: "completed",
        eye_exam: { ...exam, diagnosis: "Novo" },
      })
    );
    await wrapper.find(".eye-exam-form").trigger("submit");
    await flushPromises();
    expect(wrapper.find(".eye-table").exists()).toBe(true);
    expect(wrapper.text()).toContain("Novo");
  });

  it("edits the consultation (final state: notes only) and maps 422", async () => {
    svc.get.mockResolvedValue(makeConsultation({ status: "cancelled" }));
    await mountView(ConsultationDetailView, "/atendimentos/11", "11");
    expect(wrapper.findAll(".status-actions__btn")).toHaveLength(0);
    await btn("Editar atendimento").at(0).trigger("click");
    await wrapper.vm.$nextTick();
    expect(wrapper.find("h2").text()).toBe("Editar atendimento");
    await wrapper.find("#cf-notes").setValue("Paciente remarcou.");
    svc.update.mockRejectedValueOnce(
      validationError({ notes: ["Muito longo."] })
    );
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(wrapper.find("#cf-notes-error").text()).toBe("Muito longo.");
    svc.update.mockResolvedValueOnce(
      makeConsultation({ status: "cancelled", notes: "Paciente remarcou." })
    );
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(svc.update.mock.calls[1][1]).toMatchObject({
      notes: "Paciente remarcou.",
      scheduled_at: "2026-11-03T14:30:00-03:00",
    });
    expect(notify).toHaveBeenLastCalledWith(
      expect.anything(),
      "success",
      "Atendimento atualizado com sucesso!"
    );
    expect(wrapper.find("form.consultation-form").exists()).toBe(false);
    expect(wrapper.text()).toContain("Paciente remarcou.");
  });

  it("not found and error states", async () => {
    svc.get.mockRejectedValueOnce(
      Object.assign(new Error("404"), {
        isAxiosError: true,
        response: { status: 404 },
      })
    );
    await mountView(ConsultationDetailView, "/atendimentos/99", "99");
    expect(wrapper.find("h1").text()).toBe("Atendimento não encontrado");
    wrapper.destroy();
    svc.get.mockRejectedValueOnce(new Error("boom"));
    await mountView(ConsultationDetailView, "/atendimentos/99", "99");
    expect(wrapper.find("h1").text()).toBe("Algo deu errado");
  });
});

describe("ConsultationCreateView", () => {
  beforeEach(() => {
    vi.mocked(patientService.get).mockResolvedValue({
      id: 7,
      name: "Ana Souza",
    } as never);
  });

  it("breadcrumb, create, toast and redirect to the detail", async () => {
    await mountView(
      ConsultationCreateView,
      "/pacientes/7/atendimentos/novo",
      "7"
    );
    const crumbs = wrapper
      .findAll(".app-breadcrumb li")
      .wrappers.map((l) => l.text());
    expect(crumbs).toEqual(["Pacientes", "Ana Souza", "Novo atendimento"]);
    expect(wrapper.find("h1").text()).toBe("Novo atendimento");
    await wrapper.find("#cf-date").setValue("03/11/2026");
    await wrapper.find("#cf-time").setValue("09:00");
    await wrapper.find("#cf-professional").setValue("Dra. Ana");
    svc.create.mockResolvedValue(makeConsultation({ id: 55 }));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(svc.create).toHaveBeenCalledWith(
      "7",
      expect.objectContaining({ scheduled_at: "2026-11-03T09:00" })
    );
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      "Atendimento agendado com sucesso!"
    );
    expect(router.currentRoute.path).toBe("/atendimentos/55");
  });

  it("maps the overlap error onto Data/Hora", async () => {
    await mountView(
      ConsultationCreateView,
      "/pacientes/7/atendimentos/novo",
      "7"
    );
    await wrapper.find("#cf-time").setValue("09:00");
    await wrapper.find("#cf-professional").setValue("Dra. Ana");
    const msg = "Este profissional já tem um atendimento neste horário.";
    svc.create.mockRejectedValue(validationError({ scheduled_at: [msg] }));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(wrapper.find("#cf-time-error").text()).toBe(msg);
    expect(wrapper.find("#cf-date").attributes("aria-invalid")).toBe("true");
    expect(notify).toHaveBeenLastCalledWith(expect.anything(), "error", msg);
    expect(router.currentRoute.path).toBe("/pacientes/7/atendimentos/novo");
  });

  it("other failures become a toast; unknown patient is not found", async () => {
    await mountView(
      ConsultationCreateView,
      "/pacientes/7/atendimentos/novo",
      "7"
    );
    await wrapper.find("#cf-time").setValue("09:00");
    await wrapper.find("#cf-professional").setValue("Dra. Ana");
    svc.create.mockRejectedValue(new Error("x"));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(notify).toHaveBeenLastCalledWith(
      expect.anything(),
      "error",
      "Não foi possível agendar o atendimento."
    );
    wrapper.destroy();
    vi.mocked(patientService.get).mockRejectedValueOnce(
      Object.assign(new Error("404"), {
        isAxiosError: true,
        response: { status: 404 },
      })
    );
    await mountView(
      ConsultationCreateView,
      "/pacientes/8/atendimentos/novo",
      "8"
    );
    expect(wrapper.find("h1").text()).toBe("Paciente não encontrado");
  });
});
