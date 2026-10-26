// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import ExportCsvButton from "../ExportCsvButton.vue";
import { patientService } from "@/services/patientService";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";
import { flushPromises, makeLocalVue } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { exportCsv: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));
vi.mock("@/utils/download", () => ({ saveBlob: vi.fn() }));

const exportCsv = vi.mocked(patientService.exportCsv);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

function mountButton(propsData: Record<string, unknown> = {}) {
  wrapper = mount(ExportCsvButton as never, { localVue, propsData });
}

beforeEach(() => {
  exportCsv.mockReset();
  vi.mocked(notify).mockReset();
  vi.mocked(saveBlob).mockReset();
});

afterEach(() => wrapper?.destroy());

describe("ExportCsvButton", () => {
  it("exports with the given params, saves the file and announces it", async () => {
    const blob = new Blob(["x"]);
    let resolve!: (v: { blob: Blob; filename: string }) => void;
    exportCsv.mockReturnValue(new Promise((r) => (resolve = r)));
    const params = { search: "Ana", sex: "F", age_min: 0 };
    mountButton({ params });

    await wrapper.find("button").trigger("click");
    expect(exportCsv).toHaveBeenCalledWith(params);
    // Loading state: still focusable (aria-disabled, not disabled).
    const button = wrapper.find("button");
    expect(button.text()).toBe("Exportando...");
    expect(button.attributes("aria-busy")).toBe("true");
    expect(button.attributes("disabled")).toBeUndefined();

    // A second click while busy is ignored.
    await button.trigger("click");
    expect(exportCsv).toHaveBeenCalledTimes(1);

    resolve({ blob, filename: "pacientes-20261026-1530.csv" });
    await flushPromises();

    expect(saveBlob).toHaveBeenCalledWith(blob, "pacientes-20261026-1530.csv");
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      "Exportação concluída: pacientes-20261026-1530.csv"
    );
    expect(wrapper.find("button").text()).toBe("Exportar CSV");
  });

  it("shows an error toast when the export fails", async () => {
    // Rejected lazily and handled by the component.
    exportCsv.mockImplementation(async () => {
      throw new Error("boom");
    });
    mountButton();
    await wrapper.find("button").trigger("click");
    await flushPromises();

    expect(saveBlob).not.toHaveBeenCalled();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Não foi possível exportar os pacientes."
    );
    expect(wrapper.find("button").text()).toBe("Exportar CSV");
  });

  it("is disabled when there is nothing to export", async () => {
    mountButton({ disabled: true });
    expect(wrapper.find("button").attributes("disabled")).toBe("disabled");
  });
});
