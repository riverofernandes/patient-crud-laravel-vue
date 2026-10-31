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

describe("PatientTrashView deletion date", () => {
  it("shows Data de exclusão in São Paulo time, whatever the browser zone", async () => {
    vi.mocked(patientService.list).mockResolvedValueOnce({
      data: [
        {
          id: 3,
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
          // 02:00 UTC = previous day 23:00 in São Paulo.
          deleted_at: "2026-10-27T02:00:00Z",
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
      },
    });
    const router = makeRouter();
    await router.push("/pacientes/lixeira");
    wrapper = mount(PatientTrashView as never, { localVue, router });
    await flushPromises();
    expect(wrapper.find("tbody").text()).toContain("26/10/2026 23:00");
  });
});

const trashed = (id: number, name: string) => ({
  id,
  name,
  cpf: "52998224725",
  rg: "123456789",
  birth_date: "1990-01-01",
  sex: "F" as const,
  phone: "11987654321",
  email: null,
  description: null,
  created_at: "",
  updated_at: "",
  deleted_at: "2026-10-27T02:00:00Z",
});

describe("PatientTrashView restore focus", () => {
  it("moves focus to the next row's Restaurar without reloading the list", async () => {
    vi.mocked(patientService.list).mockResolvedValue({
      data: [trashed(1, "Ana"), trashed(2, "Bia"), trashed(3, "Caio")],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 3,
        from: 1,
        to: 3,
      },
    });
    vi.mocked(patientService.restore).mockResolvedValue({
      patient: trashed(2, "Bia") as never,
      cancelledConsultations: [],
    });
    const router = makeRouter();
    await router.push("/pacientes/lixeira");
    // Like the app: the restore util emits on the root instance.
    const root = new (makeLocalVue())();
    wrapper = mount(PatientTrashView as never, {
      localVue,
      router,
      parentComponent: root as never,
      attachTo: document.body,
    });
    await flushPromises();
    const calls = vi.mocked(patientService.list).mock.calls.length;

    await wrapper.find('[aria-label="Restaurar Bia"]').trigger("click");
    await flushPromises();

    expect(vi.mocked(patientService.list).mock.calls.length).toBe(calls);
    expect(wrapper.findAll(".restore-btn").length).toBe(2);
    expect(document.activeElement?.getAttribute("aria-label")).toBe(
      "Restaurar Caio"
    );
  });

  it("reloads when a patient is restored elsewhere (Desfazer toast)", async () => {
    const router = makeRouter();
    await router.push("/pacientes/lixeira");
    wrapper = mount(PatientTrashView as never, { localVue, router });
    await flushPromises();
    const calls = vi.mocked(patientService.list).mock.calls.length;
    wrapper.vm.$root.$emit("patient:restored", { id: 99 });
    await flushPromises();
    expect(vi.mocked(patientService.list).mock.calls.length).toBe(calls + 1);
  });
});

describe("PatientTrashView search", () => {
  it("keeps a search typed within the debounce when paginating", async () => {
    vi.useFakeTimers();
    try {
      vi.mocked(patientService.list).mockResolvedValue({
        data: [trashed(1, "Ana")],
        meta: {
          current_page: 1,
          last_page: 3,
          per_page: 10,
          total: 25,
          from: 1,
          to: 10,
        },
      });
      const router = makeRouter();
      await router.push("/pacientes/lixeira");
      wrapper = mount(PatientTrashView as never, { localVue, router });
      await flushPromises();
      await wrapper.find("#trash-search").setValue("Ana");
      vi.advanceTimersByTime(100);
      (wrapper.vm as unknown as { goToPage(p: number): void }).goToPage(2);
      await flushPromises();
      // New term: back to page 1 with the typed search, input untouched.
      expect(router.currentRoute.query).toEqual({ search: "Ana" });
      expect(
        (wrapper.find("#trash-search").element as HTMLInputElement).value
      ).toBe("Ana");
      vi.advanceTimersByTime(1000);
      await flushPromises();
      expect(router.currentRoute.query).toEqual({ search: "Ana" });
    } finally {
      vi.useRealTimers();
    }
  });
});
