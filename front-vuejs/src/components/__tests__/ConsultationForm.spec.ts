// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import ConsultationForm, {
  validateConsultationForm,
} from "../ConsultationForm.vue";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue } from "@/test/helpers";
import { makeConsultation } from "@/test/fixtures";

vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

function mountForm(propsData: object = {}) {
  wrapper = mount(ConsultationForm as never, {
    localVue,
    propsData: {
      title: "Novo atendimento",
      submitLabel: "Agendar atendimento",
      ...propsData,
    },
    attachTo: document.body,
  });
}

async function fill(id: string, value: string) {
  const input = wrapper.find(`#${id}`);
  await input.setValue(value);
}

beforeEach(() => {
  vi.mocked(notify).mockReset();
});
afterEach(() => wrapper?.destroy());

describe("validateConsultationForm", () => {
  const base = {
    date: "03/11/2026",
    time: "14:30",
    duration: 30,
    professional: "Dra. Ana",
    crm: "",
    reason: "",
    notes: "",
  };
  it("accepts a valid form", () => {
    expect(validateConsultationForm(base)).toEqual({
      errors: {},
      missingRequired: false,
    });
  });
  it("flags required, invalid and short values", () => {
    expect(
      validateConsultationForm({
        ...base,
        date: "",
        time: "",
        professional: "",
      })
    ).toEqual({
      errors: {
        date: "Campo obrigatório",
        time: "Campo obrigatório",
        professional_name: "Campo obrigatório",
      },
      missingRequired: true,
    });
    expect(
      validateConsultationForm({
        ...base,
        date: "31/02/2026",
        time: "25:00",
        professional: "Al",
        duration: 5,
        crm: "x".repeat(21),
      }).errors
    ).toEqual({
      date: "Data inválida",
      time: "Hora inválida",
      duration_minutes: "Duração inválida",
      professional_name: "Informe ao menos 3 caracteres",
      professional_crm: "Máximo de 20 caracteres",
    });
  });
});

describe("ConsultationForm", () => {
  it("has the fields, durations and optional markers", () => {
    mountForm();
    expect(wrapper.find("h1").text()).toBe("Novo atendimento");
    const labels = wrapper
      .findAll("label.form-field__label")
      .wrappers.map((l) => l.text().replace(/\s+/g, " "));
    expect(labels).toEqual([
      "Data",
      "Hora",
      "Duração",
      "Profissional",
      "CRM (Opcional)",
      "Queixa principal (Opcional)",
      "Anotações (Opcional)",
    ]);
    const options = wrapper
      .findAll("#cf-duration option")
      .wrappers.map((o) => o.text());
    expect(options).toEqual([
      "15 minutos",
      "30 minutos",
      "45 minutos",
      "60 minutos",
      "90 minutos",
    ]);
    expect(
      (wrapper.find("#cf-duration").element as HTMLSelectElement).value
    ).toBe("30");
  });

  it("blocks submit with client errors and focuses the first one", async () => {
    mountForm();
    await fill("cf-date", "");
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(wrapper.emitted("submit")).toBeFalsy();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Preencha os campos obrigatórios para salvar!"
    );
    expect(wrapper.find("#cf-date").attributes("aria-invalid")).toBe("true");
    expect(wrapper.find("#cf-date-error").text()).toBe("Campo obrigatório");
    expect(document.activeElement?.id).toBe("cf-date");
  });

  it("emits the payload with a São Paulo local datetime (no offset)", async () => {
    mountForm();
    await fill("cf-date", "03112026");
    await fill("cf-time", "14:30");
    await wrapper.find("#cf-duration").setValue("45");
    await fill("cf-professional", "  Dra. Ana  ");
    await fill("cf-crm", "123/SP");
    await fill("cf-reason", " Dor ");
    await wrapper.find("form").trigger("submit");
    expect(wrapper.emitted("submit")?.[0][0]).toEqual({
      scheduled_at: "2026-11-03T14:30",
      duration_minutes: 45,
      professional_name: "Dra. Ana",
      professional_crm: "123/SP",
      reason: "Dor",
      notes: null,
    });
  });

  it("maps the overlap 422 (scheduled_at) onto Data/Hora", async () => {
    mountForm();
    const message = "Este profissional já tem um atendimento neste horário.";
    await wrapper.setProps({
      serverErrors: {
        scheduled_at: [message],
        professional_name: ["Inválido"],
      },
    });
    await flushPromises();
    expect(wrapper.find("#cf-time-error").text()).toBe(message);
    expect(wrapper.find("#cf-time").attributes("aria-invalid")).toBe("true");
    expect(wrapper.find("#cf-date").attributes("aria-invalid")).toBe("true");
    expect(wrapper.find("#cf-date").attributes("aria-describedby")).toBe(
      "cf-time-error"
    );
    expect(wrapper.find("#cf-professional-error").text()).toBe("Inválido");
    // Editing the time clears the scheduling error.
    await fill("cf-time", "15:00");
    expect(wrapper.find("#cf-time-error").exists()).toBe(false);
  });

  it("final states: only notes editable, other fields echoed exactly", async () => {
    const consultation = makeConsultation({
      status: "completed",
      duration_minutes: 20,
      professional_crm: null,
    });
    mountForm({
      consultation,
      title: "Editar atendimento",
      headingTag: "h2",
      submitLabel: "Salvar alterações",
    });
    expect(wrapper.text()).toContain("apenas as anotações podem ser alteradas");
    expect(wrapper.find("#cf-date").attributes("disabled")).toBe("disabled");
    expect(wrapper.find("#cf-professional").attributes("disabled")).toBe(
      "disabled"
    );
    expect(wrapper.find("#cf-notes").attributes("disabled")).toBeUndefined();
    // Unusual duration still selectable.
    expect(
      wrapper.findAll("#cf-duration option").wrappers.map((o) => o.text())
    ).toContain("20 minutos");
    await fill("cf-notes", "Retorno em 6 meses");
    await wrapper.find("form").trigger("submit");
    expect(wrapper.emitted("submit")?.[0][0]).toEqual({
      scheduled_at: "2026-11-03T14:30:00-03:00",
      duration_minutes: 20,
      professional_name: "Dra. Helena Prado",
      professional_crm: null,
      reason: "Visão embaçada",
      notes: "Retorno em 6 meses",
    });
  });

  it("editable consultation is prefilled from the API values", () => {
    mountForm({ consultation: makeConsultation() });
    expect((wrapper.find("#cf-date").element as HTMLInputElement).value).toBe(
      "03/11/2026"
    );
    expect((wrapper.find("#cf-time").element as HTMLInputElement).value).toBe(
      "14:30"
    );
    expect(wrapper.find("#cf-date").attributes("disabled")).toBeUndefined();
    wrapper.find("button.btn-link-danger").trigger("click");
    expect(wrapper.emitted("cancel")).toBeTruthy();
  });
});
