// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import PatientDetailView from "../PatientDetailView.vue";
import { patientService, type Patient } from "@/services/patientService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: {
    get: vi.fn(),
    audits: vi.fn(),
    remove: vi.fn(),
    restore: vi.fn(),
  },
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
