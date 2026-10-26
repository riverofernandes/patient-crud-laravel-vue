// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import PatientListView from "../PatientListView.vue";
import { patientService, type Patient } from "@/services/patientService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: {
    list: vi.fn(),
    remove: vi.fn(),
    restore: vi.fn(),
    exportCsv: vi.fn(),
  },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const list = vi.mocked(patientService.list);
const localVue = makeLocalVue();

const patients: Patient[] = [
  {
    id: 1,
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
  },
];

let wrapper: Wrapper<Vue>;
let router: VueRouter;

async function mountList(path = "/") {
  router = makeRouter();
  await router.push(path).catch(() => undefined);
  // `as never`: test-utils v1 typings and Vue 2.7 defineComponent disagree.
  wrapper = mount(PatientListView as never, {
    localVue,
    router,
    attachTo: document.body,
  });
  await flushPromises();
}

const th = (label: string) =>
  wrapper
    .findAll("th")
    .filter((w) => w.text() === label)
    .at(0);

beforeEach(() => {
  vi.useFakeTimers();
  list.mockReset();
  list.mockResolvedValue({
    data: patients,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 1,
      from: 1,
      to: 1,
    },
  });
});

afterEach(() => {
  wrapper?.destroy();
  vi.useRealTimers();
});

describe("PatientListView", () => {
  it("loads the first page sorted by name and shows the age", async () => {
    await mountList();
    expect(list).toHaveBeenLastCalledWith({
      search: undefined,
      page: 1,
      per_page: 10,
      sort: "name",
      direction: "asc",
    });
    expect(wrapper.text()).toContain("Ana Souza");
    expect(wrapper.text()).toContain("36 anos");
  });

  it("debounces the search and writes it to the query (back to page 1)", async () => {
    await mountList("/?page=2");
    await wrapper.find("#patient-search").setValue("Ana");

    vi.advanceTimersByTime(399);
    expect(router.currentRoute.query.search).toBeUndefined();
    vi.advanceTimersByTime(1);
    await flushPromises();

    expect(router.currentRoute.query).toEqual({ search: "Ana" });
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: "Ana", page: 1 })
    );
  });

  it("toggles sorting from the column headers (aria-sort + query)", async () => {
    await mountList("/?page=3");
    expect(th("Nome do paciente").attributes("aria-sort")).toBe("ascending");
    expect(th("Data de nascimento").attributes("aria-sort")).toBe("none");

    await th("Nome do paciente").find("button").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({
      sort: "name",
      direction: "desc",
    });
    expect(th("Nome do paciente").attributes("aria-sort")).toBe("descending");

    await th("Data de nascimento").find("button").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({
      sort: "birth_date",
      direction: "asc",
    });
    expect(th("Data de nascimento").attributes("aria-sort")).toBe("ascending");
    expect(th("Nome do paciente").attributes("aria-sort")).toBe("none");
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({ sort: "birth_date", direction: "asc", page: 1 })
    );
  });

  it("keeps search and sort when changing items per page", async () => {
    await mountList("/?search=Ana&sort=birth_date&direction=desc&page=2");
    await wrapper.find("#per-page").setValue("25");
    await flushPromises();

    expect(router.currentRoute.query).toEqual({
      search: "Ana",
      per_page: "25",
      sort: "birth_date",
      direction: "desc",
    });
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({ per_page: 25, page: 1 })
    );
  });

  it("ignores an unsupported per_page in the URL", async () => {
    await mountList("/?per_page=1000");
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({ per_page: 10 })
    );
  });
});

describe("PatientListView filters and export", () => {
  const toggle = () => wrapper.find(".filters-toggle");

  it("starts with the panel closed and no badge", async () => {
    await mountList();
    expect(toggle().attributes("aria-expanded")).toBe("false");
    expect(toggle().attributes("aria-controls")).toBe("patient-filters");
    expect(wrapper.find("#patient-filters").isVisible()).toBe(false);
    expect(toggle().text().replace(/\s+/g, " ").trim()).toBe("Filtros");

    await toggle().trigger("click");
    expect(toggle().attributes("aria-expanded")).toBe("true");
    expect(wrapper.find("#patient-filters").isVisible()).toBe(true);
  });

  it("links the CPF hint to the search box without changing its name", async () => {
    await mountList();
    const input = wrapper.find("#patient-search");
    expect(input.attributes("aria-describedby")).toBe("patient-search-help");
    expect(wrapper.find("#patient-search-help").text()).toBe(
      "CPF: informe os 11 dígitos"
    );
    expect(input.attributes("placeholder")).toBe(
      "Buscar por nome do paciente ou CPF"
    );
  });

  it("reads filters from the URL: panel open, badge, API params", async () => {
    await mountList("/?sex=F&age_min=0&age_max=60&search=Ana");
    expect(toggle().attributes("aria-expanded")).toBe("true");
    expect(wrapper.find(".filters-toggle__badge").text()).toBe("3");
    expect(toggle().find(".sr-only").text()).toBe("(3 ativos)");
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({
        search: "Ana",
        sex: "F",
        age_min: 0,
        age_max: 60,
        page: 1,
      })
    );
  });

  it("pushes filter changes to the query, back to page 1, keeping search/sort/per_page", async () => {
    await mountList(
      "/?search=Ana&page=3&per_page=25&sort=birth_date&direction=desc"
    );
    await toggle().trigger("click");
    await wrapper.find("#filter-sex").setValue("M");
    await flushPromises();

    expect(router.currentRoute.query).toEqual({
      search: "Ana",
      per_page: "25",
      sort: "birth_date",
      direction: "desc",
      sex: "M",
    });

    const min = wrapper.find("#filter-age-min");
    await min.setValue("18");
    await min.trigger("change");
    await flushPromises();
    expect(router.currentRoute.query).toMatchObject({
      sex: "M",
      age_min: "18",
    });
    expect(list).toHaveBeenLastCalledWith(
      expect.objectContaining({ sex: "M", age_min: 18, page: 1 })
    );
  });

  it("keeps filters when sorting or paginating", async () => {
    await mountList("/?sex=F");
    await th("Nome do paciente").find("button").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({
      sex: "F",
      sort: "name",
      direction: "desc",
    });
  });

  it("does not push an invalid range", async () => {
    await mountList("/?age_min=50");
    const max = wrapper.find("#filter-age-max");
    await max.setValue("20");
    await max.trigger("change");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({ age_min: "50" });
    expect(wrapper.find("#filter-age-max-error").exists()).toBe(true);
  });

  it("Limpar filtros removes only the filters", async () => {
    await mountList("/?search=Ana&sex=F&age_max=40");
    const clear = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Limpar filtros")
      .at(0);
    await clear.trigger("click");
    await flushPromises();
    expect(router.currentRoute.query).toEqual({ search: "Ana" });
    expect(wrapper.find(".filters-toggle__badge").exists()).toBe(false);
  });

  it("mentions filters in the empty state", async () => {
    list.mockResolvedValue({
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
    await mountList("/?sex=M");
    expect(wrapper.text()).toContain(
      "Nenhum paciente corresponde aos filtros aplicados. Ajuste ou limpe os filtros."
    );
    // Nothing to export.
    const exportBtn = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Exportar CSV")
      .at(0);
    expect(exportBtn.attributes("disabled")).toBe("disabled");
  });

  it("keeps the original copy when only searching", async () => {
    list.mockResolvedValue({
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
    await mountList("/?search=zzz");
    expect(wrapper.text()).toContain("Tente buscar por outro nome ou CPF.");
  });

  it("exports with the current search, filters and sort (no pagination)", async () => {
    vi.mocked(patientService.exportCsv).mockResolvedValue({
      blob: new Blob(["x"]),
      filename: "pacientes.csv",
    });
    Object.assign(URL, {
      createObjectURL: vi.fn(() => "blob:x"),
      revokeObjectURL: vi.fn(),
    });
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, "click")
      .mockImplementation(() => undefined);
    await mountList(
      "/?search=Ana&sex=F&age_min=0&page=2&per_page=25&sort=birth_date&direction=desc"
    );
    const exportBtn = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Exportar CSV")
      .at(0);
    await exportBtn.trigger("click");
    await flushPromises();
    expect(patientService.exportCsv).toHaveBeenCalledWith({
      search: "Ana",
      sort: "birth_date",
      direction: "desc",
      sex: "F",
      age_min: 0,
    });
    expect(click).toHaveBeenCalled();
    click.mockRestore();
  });
});
