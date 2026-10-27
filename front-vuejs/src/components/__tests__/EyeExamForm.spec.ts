// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import EyeExamForm from "../EyeExamForm.vue";
import EyeExamView from "../EyeExamView.vue";
import { consultationService } from "@/services/consultationService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue } from "@/test/helpers";
import { exam, makeConsultation, validationError } from "@/test/fixtures";

vi.mock("@/services/consultationService", () => ({
  consultationService: { saveEyeExam: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const saveEyeExam = vi.mocked(consultationService.saveEyeExam);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;
const MINUS = "−";

function mountForm(propsData: object = {}) {
  wrapper = mount(EyeExamForm as never, {
    localVue,
    propsData: { consultationId: 11, ...propsData },
    attachTo: document.body,
  });
}
const input = (id: string) => wrapper.find(`#${id}`);
const value = (id: string) => (input(id).element as HTMLInputElement).value;

beforeEach(() => {
  saveEyeExam.mockReset();
  vi.mocked(notify).mockReset();
});
afterEach(() => wrapper?.destroy());

describe("EyeExamForm", () => {
  it("renders one fieldset per eye with unique ids", () => {
    mountForm();
    const legends = wrapper
      .findAll("legend")
      .wrappers.map((l) => l.text().replace(/\s+/g, " "));
    expect(legends).toEqual(["OD Olho direito (OD)", "OE Olho esquerdo (OE)"]);
    ["od", "oe"].forEach((eye) =>
      [
        "visual-acuity-sc",
        "visual-acuity-cc",
        "spherical",
        "cylindrical",
        "axis",
        "addition",
        "iop",
      ].forEach((f) =>
        expect(wrapper.findAll(`#ee-${eye}-${f}`)).toHaveLength(1)
      )
    );
  });

  it("enables the axis only when the cylinder is not zero, and clears it back", async () => {
    mountForm();
    expect(input("ee-od-axis").attributes("disabled")).toBe("disabled");
    await input("ee-od-cylindrical").setValue("-0.75");
    expect(input("ee-od-axis").attributes("disabled")).toBeUndefined();
    await input("ee-od-axis").setValue("90");
    await input("ee-od-cylindrical").setValue("0");
    expect(input("ee-od-axis").attributes("disabled")).toBe("disabled");
    expect(value("ee-od-axis")).toBe("");
  });

  it("steps with arrow keys and buttons and formats the sign on blur", async () => {
    mountForm();
    const sph = input("ee-od-spherical");
    await sph.trigger("keydown", { key: "ArrowDown" });
    await sph.trigger("keydown", { key: "ArrowDown" });
    expect(value("ee-od-spherical")).toBe(`${MINUS}0.50`);
    const plus = wrapper.findAll(
      "button[aria-label='Aumentar esférico do olho direito em 0.25']"
    );
    await plus.at(0).trigger("click");
    expect(value("ee-od-spherical")).toBe(`${MINUS}0.25`);
    await wrapper
      .find("button[aria-label='Aumentar adição do olho esquerdo em 0.25']")
      .trigger("click");
    expect(value("ee-oe-addition")).toBe("+0.75");
    await sph.setValue("1,5");
    await sph.trigger("blur");
    expect(value("ee-od-spherical")).toBe("+1.50");
  });

  it("validates before saving and focuses the first error", async () => {
    mountForm();
    await input("ee-od-cylindrical").setValue("-0.30");
    await input("ee-oe-iop").setValue("99");
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(saveEyeExam).not.toHaveBeenCalled();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Verifique os campos destacados."
    );
    expect(wrapper.find("#ee-od-cylindrical-error").text()).toBe(
      "Use passos de 0.25"
    );
    expect(wrapper.find("#ee-od-axis-error").text()).toBe("Informe o eixo");
    expect(wrapper.find("#ee-oe-iop-error").text()).toBe(
      "Inteiro entre 5 e 60 mmHg"
    );
    expect(input("ee-oe-iop").attributes("aria-describedby")).toContain(
      "ee-oe-iop-error"
    );
    expect(document.activeElement?.id).toBe("ee-od-cylindrical");
  });

  it("keeps only digits in acuity/axis/IOP", async () => {
    mountForm();
    await input("ee-od-visual-acuity-sc").setValue("4a");
    expect(value("ee-od-visual-acuity-sc")).toBe("4");
    await input("ee-od-iop").setValue("1x5");
    expect(value("ee-od-iop")).toBe("15");
  });

  it("saves the payload and toasts", async () => {
    const saved = makeConsultation({ status: "completed", eye_exam: exam });
    saveEyeExam.mockResolvedValue(saved);
    mountForm({ exam });
    expect(value("ee-od-spherical")).toBe(`${MINUS}1.25`);
    expect(value("ee-od-visual-acuity-sc")).toBe("40");
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(saveEyeExam).toHaveBeenCalledWith(11, {
      od: exam.od,
      oe: exam.oe,
      diagnosis: "Miopia leve.",
      observations: null,
    });
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      "Prontuário salvo com sucesso!"
    );
    expect(wrapper.emitted("saved")?.[0][0]).toBe(saved);
  });

  it("maps the API 422 onto the fields; status errors become a toast", async () => {
    saveEyeExam.mockRejectedValueOnce(
      validationError({
        "od.axis": ["O eixo é obrigatório."],
        diagnosis: ["Muito longo."],
      })
    );
    mountForm({ exam });
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(wrapper.find("#ee-od-axis-error").text()).toBe(
      "O eixo é obrigatório."
    );
    expect(wrapper.find("#ee-diagnosis-error").text()).toBe("Muito longo.");
    expect(notify).toHaveBeenLastCalledWith(
      expect.anything(),
      "error",
      "Verifique os campos destacados para salvar!"
    );

    const msg =
      "O prontuário só pode ser registrado em atendimentos realizados.";
    saveEyeExam.mockRejectedValueOnce(validationError({ status: [msg] }));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(notify).toHaveBeenLastCalledWith(expect.anything(), "error", msg);

    saveEyeExam.mockRejectedValueOnce(new Error("boom"));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(notify).toHaveBeenLastCalledWith(
      expect.anything(),
      "error",
      "Não foi possível salvar o prontuário."
    );
  });

  it("is cancelable when editing a saved exam", async () => {
    mountForm({ exam, cancelable: true });
    const cancel = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Cancelar");
    await cancel.at(0).trigger("click");
    expect(wrapper.emitted("cancel")).toBeTruthy();
  });
});

describe("EyeExamView", () => {
  it("shows a read-only OD/OE table with formatted values", () => {
    const view = mount(EyeExamView as never, { localVue, propsData: { exam } });
    const rows = view
      .findAll("tbody tr")
      .wrappers.map((r) => r.findAll("th, td").wrappers.map((c) => c.text()));
    expect(rows).toEqual([
      ["Acuidade sem correção", "20/40", "20/50"],
      ["Acuidade com correção", "20/20", "20/20"],
      ["Esférico", `${MINUS}1.25`, `${MINUS}1.00`],
      ["Cilíndrico", `${MINUS}0.50`, "0.00"],
      ["Eixo", "180°", "—"],
      ["Adição", "—", "—"],
      ["PIO", "14 mmHg", "15 mmHg"],
    ]);
    expect(view.text()).toContain("Miopia leve.");
    expect(view.text()).toContain("Atualizado em 03/11/2026 às 15:00");
  });
});
