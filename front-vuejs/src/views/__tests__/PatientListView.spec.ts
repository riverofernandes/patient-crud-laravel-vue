// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import PatientListView from "../PatientListView.vue";
import { patientService, type Patient } from "@/services/patientService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { list: vi.fn(), remove: vi.fn(), restore: vi.fn() },
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
