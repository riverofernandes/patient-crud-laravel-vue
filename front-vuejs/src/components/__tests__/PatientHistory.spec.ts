// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientHistory from "../PatientHistory.vue";
import { patientService, type Audit } from "@/services/patientService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { audits: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const audits = vi.mocked(patientService.audits);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

const updated: Audit = {
  id: 3,
  event: "updated",
  changes: {
    phone: { old: "11900000000", new: "11911111111" },
    name: { old: "Ana Souza", new: "Ana Souza Lima" },
    email: { old: null, new: "ana@example.com" },
    sex: { old: "F", new: "M" },
  },
  ip: "172.18.0.1",
  created_at: "2026-10-27T10:00:00-03:00",
};
const created: Audit = {
  id: 1,
  event: "created",
  changes: { name: { old: null, new: "Ana Souza" } },
  created_at: "2026-10-01T08:00:00-03:00",
};
const deleted: Audit = {
  id: 2,
  event: "deleted",
  changes: [],
  created_at: "2026-10-02T08:00:00-03:00",
};

const page = (data: Audit[], current: number, last: number, total: number) => ({
  data,
  meta: {
    current_page: current,
    last_page: last,
    per_page: 10,
    total,
    from: 1,
    to: data.length,
  },
});

async function mountHistory() {
  wrapper = mount(PatientHistory as never, {
    localVue,
    propsData: { patientId: "7" },
    attachTo: document.body,
  });
  await flushPromises();
}

const items = () => wrapper.findAll(".history-item");

beforeEach(() => {
  audits.mockReset();
  vi.mocked(notify).mockReset();
});

afterEach(() => wrapper?.destroy());

describe("PatientHistory", () => {
  it("shows a loading state, then the entries with formatted changes", async () => {
    audits.mockResolvedValue(page([updated, deleted, created], 1, 1, 3));
    wrapper = mount(PatientHistory as never, {
      localVue,
      propsData: { patientId: "7" },
    });
    expect(wrapper.find("[role='status']").text()).toContain(
      "Carregando histórico..."
    );
    await flushPromises();

    expect(audits).toHaveBeenCalledWith("7", { page: 1, per_page: 10 });
    expect(items()).toHaveLength(3);

    const edit = items().at(0);
    expect(edit.find(".history-item__title").text()).toMatch(
      /^Edição,\s*\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/
    );
    expect(edit.find("time").attributes("datetime")).toBe(updated.created_at);
    // São Paulo wall clock (the fixture carries the −03:00 offset).
    expect(edit.find("time").text()).toBe("27/10/2026 10:00");
    const rows = edit
      .findAll(".history-change")
      .wrappers.map((w) => w.text().replace(/\s+/g, " "));
    // Detail page order, pt-BR labels, UI formatting, "—" for empty.
    expect(rows).toEqual([
      "Nome completo: de Ana Souza para Ana Souza Lima",
      "Sexo biológico: de Feminino para Masculino",
      "Celular: de (11) 90000-0000 para (11) 91111-1111",
      "E-mail: de — para ana@example.com",
    ]);
    // IP is not shown.
    expect(wrapper.text()).not.toContain("172.18.0.1");

    expect(items().at(1).text()).toContain("Exclusão");
    expect(items().at(1).text()).toContain("Paciente movido para a lixeira.");
    expect(items().at(2).text()).toContain("Cadastro");
    expect(items().at(2).find(".history-changes").exists()).toBe(false);
    expect(wrapper.text()).toContain("Mostrando 3 de 3 registros");
    expect(wrapper.text()).not.toContain("Carregar mais");
  });

  it("loads more pages, skipping duplicates, and focuses the first new entry", async () => {
    const first = Array.from({ length: 10 }, (_, i) => ({
      ...deleted,
      id: 100 - i,
    }));
    audits.mockResolvedValueOnce(page(first, 1, 2, 12));
    await mountHistory();
    expect(wrapper.text()).toContain("Mostrando 10 de 12 registros");

    // id 91 repeats (a new audit shifted the pages).
    audits.mockResolvedValueOnce(
      page([{ ...deleted, id: 91 }, updated, created], 2, 2, 13)
    );
    const more = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Carregar mais")
      .at(0);
    await more.trigger("click");
    expect(audits).toHaveBeenLastCalledWith("7", { page: 2, per_page: 10 });
    await flushPromises();
    await wrapper.vm.$nextTick();

    expect(items()).toHaveLength(12);
    expect(wrapper.text()).toContain("Mostrando 12 de 13 registros");
    expect(wrapper.text()).not.toContain("Carregar mais");
    expect((document.activeElement as HTMLElement).dataset.auditId).toBe("3");
  });

  it("toasts when loading more fails and keeps the button", async () => {
    audits.mockResolvedValueOnce(page([created], 1, 2, 11));
    await mountHistory();
    audits.mockImplementationOnce(async () => {
      throw new Error("x");
    });
    await wrapper.find(".history-footer__more").trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Não foi possível carregar mais registros."
    );
    expect(wrapper.find(".history-footer__more").exists()).toBe(true);
  });

  it("shows the empty state", async () => {
    audits.mockResolvedValue(page([], 1, 1, 0));
    await mountHistory();
    expect(wrapper.text()).toContain("Nenhuma alteração registrada");
  });

  it("shows an error with retry", async () => {
    audits.mockImplementationOnce(async () => {
      throw new Error("x");
    });
    await mountHistory();
    const alert = wrapper.find("[role='alert']");
    expect(alert.text()).toContain("Não foi possível carregar o histórico");

    audits.mockResolvedValueOnce(page([created], 1, 1, 1));
    await alert.find("button").trigger("click");
    await flushPromises();
    expect(items()).toHaveLength(1);
  });
});

describe("PatientHistory time zone", () => {
  it("an audit at 02:00Z shows the previous day 23:00 (São Paulo)", async () => {
    audits.mockResolvedValueOnce(
      page([{ ...created, created_at: "2026-10-27T02:00:00Z" }], 1, 1, 1)
    );
    await mountHistory();
    expect(wrapper.find(".history-item time").text()).toBe("26/10/2026 23:00");
  });
});
