// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import PatientDetailView from "../PatientDetailView.vue";
import { patientService, type Patient } from "@/services/patientService";
import { consultationService } from "@/services/consultationService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: {
    get: vi.fn(),
    audits: vi.fn(),
    remove: vi.fn(),
    restore: vi.fn(),
  },
}));
vi.mock("@/services/consultationService", () => ({
  consultationService: { listByPatient: vi.fn(), timeline: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const localVue = makeLocalVue();
const patient: Patient = {
  id: 7,
  name: "Ana Souza",
  cpf: "52998224725",
  rg: "123456789",
  birth_date: "1990-01-01",
  sex: "F",
  phone: "11987654321",
  email: null,
  description: null,
  created_at: "",
  updated_at: "",
  age: 36,
};

let wrapper: Wrapper<Vue>;
let router: VueRouter;

async function mountDetail(path = "/pacientes/7") {
  router = makeRouter();
  await router.push(path).catch(() => undefined);
  wrapper = mount(PatientDetailView as never, {
    localVue,
    router,
    propsData: { id: "7" },
    attachTo: document.body,
  });
  await flushPromises();
  await wrapper.vm.$nextTick();
}

const tab = (name: string) =>
  wrapper
    .findAll("[role='tab']")
    .filter((w) => w.text() === name)
    .at(0);

beforeEach(() => {
  vi.mocked(patientService.get).mockResolvedValue(patient);
  vi.mocked(patientService.audits).mockReset();
  vi.mocked(patientService.audits).mockResolvedValue({
    data: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
      from: null,
      to: null,
    },
  });
});

afterEach(() => wrapper?.destroy());

describe("PatientDetailView tabs", () => {
  it("opens on Dados and loads the history only when Histórico opens", async () => {
    await mountDetail();
    expect(wrapper.find("h1").text()).toBe("Ana Souza");
    expect(tab("Dados").attributes("aria-selected")).toBe("true");
    expect(patientService.audits).not.toHaveBeenCalled();

    await tab("Histórico").trigger("click");
    await flushPromises();
    expect(tab("Histórico").attributes("aria-selected")).toBe("true");
    expect(router.currentRoute.query).toEqual({ aba: "historico" });
    expect(patientService.audits).toHaveBeenCalledTimes(1);
    expect(wrapper.text()).toContain("Nenhuma alteração registrada");

    // Back to Dados and again: kept, not reloaded.
    await tab("Dados").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({});
    await tab("Histórico").trigger("click");
    await flushPromises();
    expect(patientService.audits).toHaveBeenCalledTimes(1);
  });

  it("opens Histórico from the URL", async () => {
    await mountDetail("/pacientes/7?aba=historico");
    expect(tab("Histórico").attributes("aria-selected")).toBe("true");
    expect(patientService.audits).toHaveBeenCalledWith("7", {
      page: 1,
      per_page: 10,
    });
  });
});

describe("PatientDetailView clinical tabs", () => {
  const empty = {
    data: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
      from: null,
      to: null,
    },
  };

  beforeEach(() => {
    vi.mocked(consultationService.listByPatient).mockReset();
    vi.mocked(consultationService.listByPatient).mockResolvedValue(empty);
    vi.mocked(consultationService.timeline).mockReset();
    vi.mocked(consultationService.timeline).mockResolvedValue(empty);
  });

  it("keeps Dados first and Histórico second, then the new tabs", async () => {
    await mountDetail();
    expect(
      wrapper.findAll("[role='tab']").wrappers.map((t) => t.text())
    ).toEqual(["Dados", "Histórico", "Atendimentos", "Linha do tempo"]);
  });

  it("deep links ?aba=atendimentos and ?aba=linha-do-tempo, lazily loaded", async () => {
    await mountDetail("/pacientes/7?aba=atendimentos");
    expect(tab("Atendimentos").attributes("aria-selected")).toBe("true");
    expect(consultationService.listByPatient).toHaveBeenCalledTimes(1);
    expect(consultationService.timeline).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("Nenhum atendimento registrado");

    await tab("Linha do tempo").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({ aba: "linha-do-tempo" });
    expect(consultationService.timeline).toHaveBeenCalledTimes(1);

    await tab("Dados").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({});
  });

  it("reloads opened Histórico and Linha do tempo after a photo change", async () => {
    await mountDetail("/pacientes/7?aba=historico");
    await tab("Linha do tempo").trigger("click");
    await flushPromises();
    expect(patientService.audits).toHaveBeenCalledTimes(1);
    expect(consultationService.timeline).toHaveBeenCalledTimes(1);

    wrapper
      .findComponent({ name: "PatientPhoto" })
      .vm.$emit("updated", { ...patient, photo_url: null });
    await flushPromises();
    expect(patientService.audits).toHaveBeenCalledTimes(2);
    expect(consultationService.timeline).toHaveBeenCalledTimes(2);
    // Atendimentos was never opened: still not loaded.
    expect(consultationService.listByPatient).not.toHaveBeenCalled();
  });

  it("ignores an unknown ?aba", async () => {
    await mountDetail("/pacientes/7?aba=xyz");
    expect(tab("Dados").attributes("aria-selected")).toBe("true");
  });
});

describe("PatientDetailView address and photo", () => {
  it("shows '—' without an address and initials without a photo", async () => {
    await mountDetail();
    const term = wrapper
      .findAll("dt")
      .filter((w) => w.text() === "Endereço")
      .at(0);
    expect(term.element.nextElementSibling?.textContent?.trim()).toBe("—");
    const avatar = wrapper.find(".patient-detail__header .patient-avatar");
    expect(avatar.attributes("data-initials")).toBe("AS");
    expect(avatar.attributes("aria-hidden")).toBe("true");
    expect(wrapper.find("h1").text()).toBe("Ana Souza");
    expect(wrapper.find("#patient-photo-pick").text()).toBe("Adicionar foto");
  });

  it("shows the formatted address and the photo with alt text", async () => {
    vi.mocked(patientService.get).mockResolvedValue({
      ...patient,
      photo_url: "http://localhost:8001/api/patients/7/photo?v=2",
      address: {
        cep: "01310100",
        street: "Avenida Paulista",
        number: "1578",
        complement: "Sala 12",
        neighborhood: "Bela Vista",
        city: "São Paulo",
        state: "SP",
      },
    });
    await mountDetail();
    expect(wrapper.text()).toContain(
      "Avenida Paulista, 1578 - Sala 12 - Bela Vista · São Paulo/SP · CEP 01310-100"
    );
    const img = wrapper.find(".patient-detail__header img");
    expect(img.attributes("alt")).toBe("Foto de Ana Souza");
    expect(img.attributes("src")).toBe(
      "http://localhost:8001/api/patients/7/photo?v=2"
    );
    expect(wrapper.find("#patient-photo-pick").text()).toBe("Trocar foto");

    // The photo component reports a removal: the header falls back to initials.
    wrapper
      .findComponent({ name: "PatientPhoto" })
      .vm.$emit("updated", { ...patient, photo_url: null });
    await wrapper.vm.$nextTick();
    expect(wrapper.find(".patient-detail__header img").exists()).toBe(false);
    expect(wrapper.find("#patient-photo-pick").text()).toBe("Adicionar foto");
  });
});
