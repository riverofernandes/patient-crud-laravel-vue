// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import ConfirmDeleteModal from "../ConfirmDeleteModal.vue";
import { patientService } from "@/services/patientService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { remove: vi.fn(), restore: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const localVue = makeLocalVue();
const patient = { id: 5, name: "João Lima" };
let wrapper: Wrapper<Vue>;

/** BootstrapVue portals the modal to <body>, so query the document. */
function button(name: string): HTMLButtonElement {
  const found = Array.from(
    document.querySelectorAll<HTMLButtonElement>(".modal button")
  ).find((b) => b.textContent?.trim() === name);
  if (!found) throw new Error(`button "${name}" not found`);
  return found;
}

async function open() {
  wrapper = mount(ConfirmDeleteModal as never, {
    localVue,
    propsData: { patient },
    attachTo: document.body,
  });
  await flushPromises();
  await wrapper.vm.$nextTick();
}

beforeEach(() => {
  vi.mocked(patientService.remove).mockReset();
  vi.mocked(patientService.restore).mockReset();
  vi.mocked(notify).mockReset();
});

afterEach(() => {
  wrapper?.destroy();
  document.body.innerHTML = "";
});

describe("ConfirmDeleteModal", () => {
  it("shows the confirmation dialog for the given patient", async () => {
    await open();
    const dialog = document.querySelector("[role='dialog']");
    expect(dialog?.textContent).toContain("Excluir paciente");
    expect(dialog?.querySelector("strong")?.textContent).toBe(
      "ele será movido para a lixeira"
    );
    expect(dialog?.textContent?.replace(/\s+/g, " ")).toContain(
      "Atenção! Ao excluir este paciente, ele será movido para a lixeira e poderá ser restaurado depois. Deseja realmente continuar?"
    );
  });

  it("Cancelar closes without deleting", async () => {
    await open();
    button("Cancelar").click();
    await flushPromises();

    expect(wrapper.emitted("update:patient")?.[0]).toEqual([null]);
    expect(patientService.remove).not.toHaveBeenCalled();
    expect(wrapper.emitted("deleted")).toBeUndefined();
  });

  it("Confirmar deletes, toasts with an undo action and emits deleted", async () => {
    vi.mocked(patientService.remove).mockResolvedValue();
    vi.mocked(patientService.restore).mockResolvedValue({ id: 5 } as never);
    await open();
    button("Confirmar").click();
    await flushPromises();

    expect(patientService.remove).toHaveBeenCalledWith(5);
    expect(wrapper.emitted("deleted")?.[0]).toEqual([patient]);
    expect(wrapper.emitted("update:patient")?.[0]).toEqual([null]);

    const [, variant, message, options] = vi.mocked(notify).mock.calls[0];
    expect(variant).toBe("success");
    expect(message).toBe("Paciente excluído com sucesso!");
    expect(options?.action?.label).toBe("Desfazer");

    // "Desfazer" restores the patient
    options?.action?.handler();
    await flushPromises();
    expect(patientService.restore).toHaveBeenCalledWith(5);
    expect(vi.mocked(notify).mock.calls[1].slice(1, 3)).toEqual([
      "success",
      "Paciente restaurado com sucesso!",
    ]);
  });

  it("keeps the dialog open and shows an error toast when the delete fails", async () => {
    vi.mocked(patientService.remove).mockRejectedValue(new Error("boom"));
    await open();
    button("Confirmar").click();
    await flushPromises();

    expect(wrapper.emitted("deleted")).toBeUndefined();
    expect(wrapper.emitted("update:patient")).toBeUndefined();
    expect(vi.mocked(notify).mock.calls[0].slice(1, 3)).toEqual([
      "error",
      "Não foi possível excluir o paciente.",
    ]);
  });
});
