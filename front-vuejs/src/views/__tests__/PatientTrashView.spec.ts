// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientTrashView from "../PatientTrashView.vue";
import { patientService } from "@/services/patientService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { list: vi.fn(), exportCsv: vi.fn(), restore: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));
vi.mock("@/utils/download", () => ({ saveBlob: vi.fn() }));

const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

beforeEach(() => {
  vi.mocked(patientService.list).mockResolvedValue({
    data: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 2,
      from: 1,
      to: 2,
    },
  });
  vi.mocked(patientService.exportCsv).mockResolvedValue({
    blob: new Blob(["x"]),
    filename: "pacientes.csv",
  });
});

afterEach(() => wrapper?.destroy());

describe("PatientTrashView export", () => {
  it("exports the trash (trashed=only) with the current search", async () => {
    const router = makeRouter();
    await router.push("/pacientes/lixeira?search=Ana&page=2");
    wrapper = mount(PatientTrashView as never, { localVue, router });
    await flushPromises();

    expect(wrapper.find("#trash-search").attributes("aria-describedby")).toBe(
      "trash-search-help"
    );
    const button = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Exportar CSV")
      .at(0);
    await button.trigger("click");
    await flushPromises();
    expect(patientService.exportCsv).toHaveBeenCalledWith({
      search: "Ana",
      trashed: "only",
    });
  });
});
