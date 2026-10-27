// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientPhoto, {
  PHOTO_REMOVED,
  PHOTO_UPDATED,
} from "../PatientPhoto.vue";
import PatientAvatar from "../PatientAvatar.vue";
import { patientService } from "@/services/patientService";
import { notify } from "@/utils/notify";
import { validationError } from "@/test/fixtures";
import { flushPromises, makeLocalVue } from "@/test/helpers";

vi.mock("@/services/patientService", () => ({
  patientService: { uploadPhoto: vi.fn(), removePhoto: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const localVue = makeLocalVue();
const PHOTO = "http://localhost:8001/api/patients/7/photo?v=3";

let wrapper: Wrapper<Vue>;

function mountPhoto(photoUrl: string | null = null) {
  wrapper = mount(PatientPhoto as never, {
    localVue,
    propsData: { patient: { id: 7, name: "Ana Souza", photo_url: photoUrl } },
    attachTo: document.body,
  });
  return wrapper;
}

function makeFile(name: string, type: string, size: number): File {
  const file = new File(["x"], name, { type });
  Object.defineProperty(file, "size", { value: size });
  return file;
}

async function choose(file: File) {
  const el = wrapper.find("input[type='file']").element as HTMLInputElement;
  Object.defineProperty(el, "files", { value: [file], configurable: true });
  await wrapper.find("input[type='file']").trigger("change");
  await flushPromises();
}

/** Modal buttons are rendered at the end of <body>, outside the wrapper. */
const buttonByText = (text: string) => ({
  trigger: async (event: string) => {
    const button = Array.from(document.querySelectorAll("button")).find(
      (b) => b.textContent?.trim() === text && b.offsetParent !== undefined
    );
    if (!button) throw new Error(`button "${text}" not found`);
    button.dispatchEvent(new Event(event));
    await flushPromises();
  },
});

beforeEach(() => {
  vi.mocked(patientService.uploadPhoto).mockReset();
  vi.mocked(patientService.removePhoto).mockReset();
  vi.mocked(notify).mockReset();
  URL.createObjectURL = vi.fn(() => "blob:preview");
  URL.revokeObjectURL = vi.fn();
});

afterEach(() => wrapper?.destroy());

describe("PatientAvatar", () => {
  it("shows the photo with alt text, or decorative initials", async () => {
    const avatar = mount(PatientAvatar as never, {
      propsData: { name: "Ana Souza", photoUrl: PHOTO, size: "lg" },
    });
    const img = avatar.find("img");
    expect(img.attributes("src")).toBe(PHOTO);
    expect(img.attributes("alt")).toBe("Foto de Ana Souza");
    expect(avatar.attributes("aria-hidden")).toBeUndefined();

    await img.trigger("error");
    expect(avatar.find("img").exists()).toBe(false);
    expect(avatar.attributes("data-initials")).toBe("AS");
    expect(avatar.attributes("aria-hidden")).toBe("true");
    expect(avatar.text()).toBe("");

    await avatar.setProps({ photoUrl: `${PHOTO}4` });
    expect(avatar.find("img").exists()).toBe(true);
    avatar.destroy();
  });

  it("is hidden and text-free when decorative", () => {
    const avatar = mount(PatientAvatar as never, {
      propsData: { name: "Ana Souza", photoUrl: PHOTO, decorative: true },
    });
    expect(avatar.attributes("aria-hidden")).toBe("true");
    expect(avatar.find("img").attributes("alt")).toBe("");
    expect(avatar.text()).toBe("");
    avatar.destroy();
  });
});

describe("PatientPhoto", () => {
  it("offers 'Adicionar foto' without a photo and 'Trocar/Remover' with one", async () => {
    mountPhoto();
    expect(wrapper.find("#patient-photo-pick").text()).toBe("Adicionar foto");
    expect(wrapper.find("#patient-photo-remove").exists()).toBe(false);
    const input = wrapper.find("input[type='file']");
    expect(input.attributes("accept")).toBe("image/jpeg,image/png,image/webp");
    const click = vi.spyOn(input.element as HTMLInputElement, "click");
    await wrapper.find("#patient-photo-pick").trigger("click");
    expect(click).toHaveBeenCalled();

    await wrapper.setProps({
      patient: { id: 7, name: "Ana Souza", photo_url: PHOTO },
    });
    expect(wrapper.find("#patient-photo-pick").text()).toBe("Trocar foto");
    expect(wrapper.find("#patient-photo-remove").text()).toBe("Remover foto");
  });

  it("rejects the wrong type and files over 2 MB before uploading", async () => {
    mountPhoto();
    await choose(makeFile("a.gif", "image/gif", 100));
    expect(wrapper.find("#patient-photo-error").text()).toBe(
      "Formato não suportado. Envie uma imagem JPEG, PNG ou WebP."
    );
    expect(wrapper.find("#patient-photo-error").attributes("role")).toBe(
      "alert"
    );

    await choose(makeFile("a.jpg", "image/jpeg", 3 * 1024 * 1024));
    expect(wrapper.find("#patient-photo-error").text()).toBe(
      "A imagem tem 3 MB. O tamanho máximo é 2 MB."
    );
    expect(patientService.uploadPhoto).not.toHaveBeenCalled();
    expect(document.body.textContent).not.toContain("Pré-visualização");
  });

  it("previews, uploads with progress and emits the updated patient", async () => {
    let resolve!: (v: unknown) => void;
    vi.mocked(patientService.uploadPhoto).mockImplementation(
      (_id, _file, options) => {
        options?.onProgress?.(40);
        return new Promise((r) => (resolve = r)) as never;
      }
    );
    mountPhoto();
    const file = makeFile("eu.png", "image/png", 1500);
    await choose(file);

    const preview = document.querySelector(
      "img[alt='Pré-visualização da nova foto']"
    );
    expect(preview?.getAttribute("src")).toBe("blob:preview");
    expect(document.body.textContent).toContain("eu.png");

    await buttonByText("Enviar foto").trigger("click");
    await flushPromises();
    expect(vi.mocked(patientService.uploadPhoto).mock.calls[0][0]).toBe(7);
    expect(vi.mocked(patientService.uploadPhoto).mock.calls[0][1]).toBe(file);
    const bar = document.querySelector("[role='progressbar']");
    expect(bar?.getAttribute("aria-valuenow")).toBe("40");
    expect(bar?.getAttribute("aria-label")).toBe("Enviando foto");

    const updated = { id: 7, name: "Ana Souza", photo_url: PHOTO };
    resolve(updated);
    await flushPromises();
    expect(wrapper.emitted("updated")?.[0]).toEqual([updated]);
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      PHOTO_UPDATED
    );
    expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:preview");
  });

  it("shows the server's 422 message in the dialog", async () => {
    vi.mocked(patientService.uploadPhoto).mockRejectedValue(
      validationError({ photo: ["A imagem deve ter ao menos 100×100 px."] })
    );
    mountPhoto();
    await choose(makeFile("small.png", "image/png", 500));
    await buttonByText("Enviar foto").trigger("click");
    await flushPromises();
    expect(document.body.textContent).toContain(
      "A imagem deve ter ao menos 100×100 px."
    );
    expect(wrapper.emitted("updated")).toBeUndefined();
  });

  it("cancels the preview without uploading", async () => {
    mountPhoto();
    await choose(makeFile("eu.png", "image/png", 1500));
    await buttonByText("Cancelar").trigger("click");
    await flushPromises();
    expect(patientService.uploadPhoto).not.toHaveBeenCalled();
    expect(URL.revokeObjectURL).toHaveBeenCalled();
  });

  it("removes the photo after confirmation", async () => {
    vi.mocked(patientService.removePhoto).mockResolvedValue();
    mountPhoto(PHOTO);
    await wrapper.find("#patient-photo-remove").trigger("click");
    await flushPromises();
    expect(document.body.textContent).toContain(
      "Deseja realmente remover a foto de Ana Souza?"
    );
    await buttonByText("Confirmar").trigger("click");
    await flushPromises();
    expect(patientService.removePhoto).toHaveBeenCalledWith(7);
    expect(wrapper.emitted("updated")?.[0]?.[0]).toMatchObject({
      photo_url: null,
    });
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      PHOTO_REMOVED
    );
  });

  it("treats 404 on removal as removed, and reports other errors", async () => {
    vi.mocked(patientService.removePhoto).mockRejectedValueOnce(
      Object.assign(new Error("500"), {
        isAxiosError: true,
        response: { status: 500, data: {} },
      })
    );
    mountPhoto(PHOTO);
    await wrapper.find("#patient-photo-remove").trigger("click");
    await buttonByText("Confirmar").trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "O servidor encontrou um erro. Tente novamente em instantes."
    );
    expect(wrapper.emitted("updated")).toBeUndefined();

    vi.mocked(patientService.removePhoto).mockRejectedValueOnce(
      Object.assign(new Error("404"), {
        isAxiosError: true,
        response: { status: 404, data: {} },
      })
    );
    await buttonByText("Confirmar").trigger("click");
    await flushPromises();
    expect(wrapper.emitted("updated")).toHaveLength(1);
  });
});
