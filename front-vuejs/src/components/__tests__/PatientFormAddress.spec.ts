// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientForm from "../PatientForm.vue";
import {
  ADDRESS_REMOVED,
  CEP_FOUND,
  CEP_NOT_FOUND,
  CEP_UNAVAILABLE,
} from "../AddressFields.vue";
import type { Address, Patient } from "@/services/patientService";
import { cepService, type CepResult } from "@/services/cepService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { checkCpf: vi.fn().mockResolvedValue({ available: true }) },
}));
vi.mock("@/services/cepService", () => ({
  cepService: { lookup: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const lookup = vi.mocked(cepService.lookup);
const localVue = makeLocalVue();

const address: Address = {
  cep: "01310100",
  street: "Avenida Paulista",
  number: "1578",
  complement: null,
  neighborhood: "Bela Vista",
  city: "São Paulo",
  state: "SP",
};

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
  address: null,
};

const found: CepResult = {
  cep: "01310100",
  street: "Avenida Paulista",
  neighborhood: "Bela Vista",
  city: "São Paulo",
  state: "sp",
};

function httpError(status: number) {
  return Object.assign(new Error(String(status)), {
    isAxiosError: true,
    response: { status, data: {} },
  });
}

let wrapper: Wrapper<Vue>;

function mountForm(props: Record<string, unknown> = {}) {
  wrapper = mount(PatientForm as never, {
    localVue,
    router: makeRouter(),
    propsData: {
      title: "Editar paciente",
      submitLabel: "Atualizar paciente",
      patient,
      ...props,
    },
    attachTo: document.body,
  });
  return wrapper;
}

const input = (field: string) =>
  wrapper.find(`#pf-address-${field}`).element as HTMLInputElement;
const errorText = (field: string) =>
  wrapper.find(`#pf-address-${field}-error`).exists()
    ? wrapper.find(`#pf-address-${field}-error`).text()
    : "";
const status = () => wrapper.find(".address-fields [role='status']").text();

async function submit() {
  await wrapper.find("form").trigger("submit");
  await flushPromises();
  return wrapper.emitted("submit")?.[0]?.[0] as
    | { address: Address | null }
    | undefined;
}

beforeEach(() => {
  lookup.mockReset();
  vi.mocked(notify).mockReset();
});

afterEach(() => wrapper?.destroy());

describe("PatientForm address", () => {
  it("renders the optional section below the existing fields (create: no remove)", () => {
    mountForm({ patient: null, title: "Cadastro de novo paciente" });
    expect(wrapper.find("#pf-address-title").text()).toMatch(
      /^Endereço\s+\(Opcional\)$/
    );
    expect(wrapper.find("label[for='pf-address-cep']").text()).toBe("CEP");
    expect(wrapper.findAll("#pf-address-state option")).toHaveLength(28);
    expect(wrapper.text()).not.toContain("Remover endereço");
  });

  it("fills the address from the CEP, moves focus to Número and announces it", async () => {
    lookup.mockResolvedValue(found);
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("01310100");
    expect(input("cep").value).toBe("01310-100");
    expect(lookup).toHaveBeenCalledTimes(1);
    expect(lookup.mock.calls[0][0]).toBe("01310100");
    await flushPromises();
    await wrapper.vm.$nextTick();

    expect(input("street").value).toBe("Avenida Paulista");
    expect(input("neighborhood").value).toBe("Bela Vista");
    expect(input("city").value).toBe("São Paulo");
    expect(
      (wrapper.find("#pf-address-state").element as HTMLSelectElement).value
    ).toBe("SP");
    expect(document.activeElement).toBe(input("number"));
    expect(status()).toBe(CEP_FOUND);

    // Blur after the lookup does not repeat it.
    await wrapper.find("#pf-address-cep").trigger("blur");
    expect(lookup).toHaveBeenCalledTimes(1);
  });

  it("shows 'CEP não encontrado' inline on 404 without blocking the form", async () => {
    lookup.mockRejectedValue(httpError(404));
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("99999999");
    await flushPromises();
    expect(errorText("cep")).toBe(CEP_NOT_FOUND);
    expect(input("cep").getAttribute("aria-describedby")).toBe(
      "pf-address-cep-error"
    );
    expect(status()).toBe(CEP_NOT_FOUND);

    // Typing again clears the message.
    await wrapper.find("#pf-address-cep").setValue("9999999");
    expect(errorText("cep")).toBe("");
  });

  it("shows the manual-fill note on 503 and still saves a typed address", async () => {
    lookup.mockRejectedValue(httpError(503));
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("01310100");
    await flushPromises();
    expect(wrapper.find("#pf-address-cep-note").text()).toBe(CEP_UNAVAILABLE);
    expect(errorText("cep")).toBe("");
    expect(input("cep").getAttribute("aria-describedby")).toBe(
      "pf-address-cep-note"
    );

    await wrapper.find("#pf-address-street").setValue("Rua A");
    await wrapper.find("#pf-address-number").setValue("S/N");
    await wrapper.find("#pf-address-neighborhood").setValue("Centro");
    await wrapper.find("#pf-address-city").setValue("Santos");
    await wrapper.find("#pf-address-state").setValue("SP");
    const payload = await submit();
    expect(payload?.address).toEqual({
      cep: "01310100",
      street: "Rua A",
      number: "S/N",
      complement: null,
      neighborhood: "Centro",
      city: "Santos",
      state: "SP",
    });
  });

  it("ignores a stale lookup when the CEP changes meanwhile", async () => {
    const resolvers: Array<(v: CepResult) => void> = [];
    lookup.mockImplementation(
      () => new Promise<CepResult>((r) => resolvers.push(r))
    );
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("01310100");
    const firstSignal = lookup.mock.calls[0][1]?.signal as AbortSignal;
    await wrapper.find("#pf-address-cep").setValue("20040020");
    expect(firstSignal.aborted).toBe(true);
    expect(lookup).toHaveBeenCalledTimes(2);

    resolvers[0](found);
    await flushPromises();
    expect(input("street").value).toBe("");

    resolvers[1]({
      ...found,
      cep: "20040020",
      street: "Rua da Assembleia",
      city: "Rio de Janeiro",
      state: "RJ",
    });
    await flushPromises();
    expect(input("street").value).toBe("Rua da Assembleia");
  });

  it("drops a pending lookup when the CEP becomes incomplete", async () => {
    let resolve!: (v: CepResult) => void;
    lookup.mockImplementation(
      () => new Promise<CepResult>((r) => (resolve = r))
    );
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("01310100");
    await wrapper.find("#pf-address-cep").setValue("0131010");
    resolve(found);
    await flushPromises();
    expect(input("street").value).toBe("");
    expect(wrapper.find(".cep-input__spinner").exists()).toBe(false);
  });

  it("requires the address fields once one is filled (complement stays optional)", async () => {
    mountForm();
    await wrapper.find("#pf-address-complement").setValue("Apto 1");
    expect(await submit()).toBeUndefined();
    ["cep", "street", "number", "neighborhood", "city", "state"].forEach(
      (field) => expect(errorText(field)).toBe("Campo obrigatório")
    );
    expect(errorText("complement")).toBe("");
    expect(input("street").getAttribute("aria-describedby")).toBe(
      "pf-address-street-error"
    );
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Preencha os campos obrigatórios para salvar!"
    );
    expect(document.activeElement).toBe(input("cep"));

    await wrapper.find("#pf-address-street").setValue("Rua B");
    expect(errorText("street")).toBe("");
  });

  it("flags an incomplete CEP", async () => {
    mountForm({ patient: { ...patient, address } });
    await wrapper.find("#pf-address-cep").setValue("0131");
    expect(await submit()).toBeUndefined();
    expect(errorText("cep")).toBe("CEP inválido");
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Verifique os campos destacados."
    );
  });

  it("sends address: null when every field is empty", async () => {
    mountForm();
    const payload = await submit();
    expect(payload).toBeDefined();
    expect(payload?.address).toBeNull();
  });

  it("loads an existing address without looking it up, and removes it", async () => {
    mountForm({ patient: { ...patient, address } });
    expect(input("cep").value).toBe("01310-100");
    expect(input("street").value).toBe("Avenida Paulista");
    await wrapper.find("#pf-address-cep").trigger("blur");
    expect(lookup).not.toHaveBeenCalled();

    const remove = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Remover endereço")
      .at(0);
    await remove.trigger("click");
    await wrapper.vm.$nextTick();
    expect(input("cep").value).toBe("");
    expect(input("street").value).toBe("");
    expect(status()).toBe(ADDRESS_REMOVED);
    expect(document.activeElement).toBe(input("cep"));
    expect(wrapper.text()).not.toContain("Remover endereço");

    const payload = await submit();
    expect(payload?.address).toBeNull();
  });

  it("sends the edited address and keeps the other fields", async () => {
    mountForm({ patient: { ...patient, address } });
    await wrapper.find("#pf-address-complement").setValue(" Sala 12 ");
    const payload = await submit();
    expect(payload).toMatchObject({
      name: "Maria Souza",
      address: { ...address, complement: "Sala 12" },
    });
  });

  it("maps 422 address.* keys (and a bare 'address') to the fields", async () => {
    mountForm({ patient: { ...patient, address } });
    await wrapper.setProps({
      serverErrors: {
        "address.cep": ["O campo CEP deve ter 8 dígitos."],
        "address.state": ["UF inválida."],
        address: ["Endereço inválido."],
        name: ["Nome inválido."],
      },
    });
    await wrapper.vm.$nextTick();
    expect(errorText("cep")).toBe("O campo CEP deve ter 8 dígitos.");
    expect(errorText("state")).toBe("UF inválida.");
    expect(wrapper.find("#pf-name-error").text()).toBe("Nome inválido.");
    expect(input("cep").getAttribute("aria-describedby")).toBe(
      "pf-address-cep-error"
    );
  });

  it("treats a network failure like an unavailable service and retries on blur", async () => {
    lookup.mockRejectedValueOnce(new Error("Network Error"));
    lookup.mockResolvedValueOnce(found);
    mountForm();
    await wrapper.find("#pf-address-cep").setValue("01310100");
    await flushPromises();
    expect(wrapper.find("#pf-address-cep-note").exists()).toBe(true);
    await wrapper.find("#pf-address-cep").trigger("blur");
    await flushPromises();
    expect(lookup).toHaveBeenCalledTimes(2);
    expect(input("street").value).toBe("Avenida Paulista");
  });
});
