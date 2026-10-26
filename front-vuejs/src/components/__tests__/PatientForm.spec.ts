// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientForm, { CPF_TAKEN } from "../PatientForm.vue";
import {
  patientService,
  type CpfAvailability,
  type Patient,
} from "@/services/patientService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { checkCpf: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const checkCpf = vi.mocked(patientService.checkCpf);
const localVue = makeLocalVue();
const VALID_CPF = "52998224725";
const OTHER_VALID_CPF = "11144477735";

const patient: Patient = {
  id: 7,
  name: "Maria Souza",
  cpf: "62518953060",
  rg: "123456789",
  birth_date: "1990-01-01",
  sex: "F",
  phone: "11987654321",
  email: null,
  description: null,
  created_at: "",
  updated_at: "",
};

let wrapper: Wrapper<Vue>;

function mountForm(props: Record<string, unknown> = {}) {
  wrapper = mount(PatientForm as never, {
    localVue,
    router: makeRouter(),
    propsData: { title: "Cadastro", submitLabel: "Salvar paciente", ...props },
    attachTo: document.body,
  });
  return wrapper;
}

async function typeCpf(value: string) {
  const input = wrapper.find("#pf-cpf");
  await input.setValue(value);
  await input.trigger("blur");
}

const cpfError = () => wrapper.find("#pf-cpf-error");

beforeEach(() => {
  vi.useFakeTimers();
  checkCpf.mockReset();
  vi.mocked(notify).mockReset();
});

afterEach(() => {
  wrapper?.destroy();
  vi.useRealTimers();
});

describe("PatientForm", () => {
  it("flags the six required fields on an empty submit and does not emit", async () => {
    mountForm();
    await wrapper.find("form").trigger("submit");

    const errors = wrapper
      .findAll(".form-field__error")
      .wrappers.map((w) => w.text());
    expect(errors.filter((t) => t === "Campo obrigatório")).toHaveLength(6);
    expect(wrapper.find("#pf-name").attributes("aria-describedby")).toBe(
      "pf-name-error"
    );
    expect(wrapper.find("#pf-email").attributes("aria-describedby")).toBe(
      undefined
    );
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Preencha os campos obrigatórios para salvar!"
    );
    expect(wrapper.emitted("submit")).toBeUndefined();
  });

  it("maps the server 422 errors onto the fields", async () => {
    mountForm();
    await wrapper.setProps({
      serverErrors: {
        cpf: ["Já existe um paciente cadastrado com este CPF."],
        birth_date: ["A data de nascimento não pode ser futura."],
      },
    });

    expect(cpfError().text()).toBe(
      "Já existe um paciente cadastrado com este CPF."
    );
    expect(wrapper.find("#pf-cpf").attributes("aria-describedby")).toBe(
      "pf-cpf-error"
    );
    expect(wrapper.find("#pf-cpf").attributes("aria-invalid")).toBe("true");
    expect(wrapper.find("#pf-birth-date-error").text()).toBe(
      "A data de nascimento não pode ser futura."
    );
  });

  it("shows the duplicate-CPF error after blur (debounced) and announces it", async () => {
    checkCpf.mockResolvedValue({ available: false, message: CPF_TAKEN });
    mountForm();
    await typeCpf(VALID_CPF);

    expect(checkCpf).not.toHaveBeenCalled(); // debounced
    vi.advanceTimersByTime(300);
    expect(checkCpf).toHaveBeenCalledTimes(1);
    expect(checkCpf.mock.calls[0][0]).toBe(VALID_CPF);
    expect(checkCpf.mock.calls[0][1]?.ignore).toBeUndefined();

    await flushPromises();
    expect(cpfError().text()).toBe(CPF_TAKEN);
    expect(wrapper.find("#pf-cpf").attributes("aria-describedby")).toBe(
      "pf-cpf-error"
    );
    expect(wrapper.find("[role='status']").text()).toBe(CPF_TAKEN);
  });

  it("does not ask the API for incomplete or invalid CPFs", async () => {
    mountForm();
    await typeCpf("529982247");
    await typeCpf("11111111111");
    vi.advanceTimersByTime(1000);
    expect(checkCpf).not.toHaveBeenCalled();
  });

  it("passes ignore=id when editing and skips the patient's own CPF", async () => {
    checkCpf.mockResolvedValue({ available: true });
    mountForm({ patient });
    await wrapper.find("#pf-cpf").trigger("blur");
    vi.advanceTimersByTime(300);
    expect(checkCpf).not.toHaveBeenCalled();

    await typeCpf(VALID_CPF);
    vi.advanceTimersByTime(300);
    expect(checkCpf).toHaveBeenCalledWith(
      VALID_CPF,
      expect.objectContaining({ ignore: 7 })
    );
    await flushPromises();
    expect(cpfError().exists()).toBe(false);
  });

  it("ignores a stale answer when the CPF changed meanwhile", async () => {
    let resolve!: (v: CpfAvailability) => void;
    checkCpf.mockImplementation(
      () => new Promise<CpfAvailability>((r) => (resolve = r))
    );
    mountForm();
    await typeCpf(VALID_CPF);
    vi.advanceTimersByTime(300);
    const signal = checkCpf.mock.calls[0][1]?.signal as AbortSignal;

    await wrapper.find("#pf-cpf").setValue(OTHER_VALID_CPF);
    expect(signal.aborted).toBe(true);
    resolve({ available: false });
    await flushPromises();
    expect(cpfError().exists()).toBe(false);
  });

  it("never blocks saving when the check fails (network error)", async () => {
    checkCpf.mockRejectedValue(new Error("Network Error"));
    mountForm({ patient });
    await typeCpf(VALID_CPF);
    vi.advanceTimersByTime(300);
    await flushPromises();
    expect(cpfError().exists()).toBe(false);

    await wrapper.find("form").trigger("submit");
    const [payload] = wrapper.emitted("submit")?.[0] ?? [];
    expect(payload).toMatchObject({ cpf: VALID_CPF, name: "Maria Souza" });
  });
});
