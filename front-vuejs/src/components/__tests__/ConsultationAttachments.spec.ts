// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import ConsultationAttachments, {
  ATTACHMENT_DELETED,
  ATTACHMENT_UPLOADED,
} from "../ConsultationAttachments.vue";
import {
  attachmentService,
  type Attachment,
} from "@/services/attachmentService";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";
import { validationError } from "@/test/fixtures";
import { flushPromises, makeLocalVue } from "@/test/helpers";

vi.mock("@/services/attachmentService", () => ({
  attachmentService: {
    list: vi.fn(),
    upload: vi.fn(),
    download: vi.fn(),
    remove: vi.fn(),
  },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));
vi.mock("@/utils/download", async () => ({
  ...(await vi.importActual<object>("@/utils/download")),
  saveBlob: vi.fn(),
}));

const svc = vi.mocked(attachmentService);
const localVue = makeLocalVue();

const pdf: Attachment = {
  id: 3,
  consultation_id: 12,
  original_name: "topografia.pdf",
  mime_type: "application/pdf",
  size: 1258291,
  description: "Topografia de córnea",
  created_at: "2026-11-03T15:10:00-03:00",
};
const png: Attachment = {
  id: 4,
  consultation_id: 12,
  original_name: "retina.png",
  mime_type: "image/png",
  size: 183245,
  description: null,
  created_at: "2026-11-03T18:05:00Z",
};

let wrapper: Wrapper<Vue>;

async function mountAttachments() {
  wrapper = mount(ConsultationAttachments as never, {
    localVue,
    propsData: { consultationId: 12 },
    attachTo: document.body,
  });
  await flushPromises();
  return wrapper;
}

function makeFile(name: string, type: string, size: number): File {
  const file = new File(["x"], name, { type });
  Object.defineProperty(file, "size", { value: size });
  return file;
}

async function choose(file: File) {
  const input = wrapper.find("#attachment-file");
  Object.defineProperty(input.element, "files", {
    value: [file],
    configurable: true,
  });
  await input.trigger("change");
}

const fileError = () =>
  wrapper.find("#attachment-file-error").exists()
    ? wrapper.find("#attachment-file-error").text()
    : "";

async function clickInBody(text: string) {
  const button = Array.from(document.querySelectorAll("button")).find(
    (b) => b.textContent?.trim() === text
  );
  if (!button) throw new Error(`button "${text}" not found`);
  button.click();
  await flushPromises();
}

function httpError(status: number) {
  return Object.assign(new Error(String(status)), {
    isAxiosError: true,
    response: { status, data: {} },
  });
}

beforeEach(() => {
  Object.values(svc).forEach((fn) => vi.mocked(fn).mockReset());
  vi.mocked(notify).mockReset();
  vi.mocked(saveBlob).mockReset();
});

afterEach(() => wrapper?.destroy());

describe("ConsultationAttachments", () => {
  it("shows the empty state", async () => {
    svc.list.mockResolvedValue([]);
    await mountAttachments();
    expect(svc.list).toHaveBeenCalledWith(12);
    expect(wrapper.find("h2").text()).toBe("Anexos");
    expect(wrapper.text()).toContain("Nenhum anexo");
  });

  it("lists attachments with icon, name, description, size and São Paulo date", async () => {
    svc.list.mockResolvedValue([pdf, png]);
    await mountAttachments();
    const items = wrapper.findAll(".attachment-item");
    expect(items).toHaveLength(2);
    const first = items.at(0);
    expect(first.find(".attachment-item__name").text()).toBe("topografia.pdf");
    expect(first.find(".attachment-item__description").text()).toBe(
      "Topografia de córnea"
    );
    expect(first.find(".attachment-item__meta").text()).toContain("1,2 MB");
    expect(first.find("time").text()).toBe("03/11/2026 15:10");
    expect(first.find(".attachment-item__icon--pdf").exists()).toBe(true);
    const second = items.at(1);
    expect(second.find(".attachment-item__icon--image").exists()).toBe(true);
    expect(second.find(".attachment-item__description").exists()).toBe(false);
    expect(second.find("time").text()).toBe("03/11/2026 15:05");
    expect(second.find(".attachment-item__meta").text()).toContain("179 KB");
    const labels = first
      .findAll("button")
      .wrappers.map((b) => b.attributes("aria-label"));
    expect(labels).toEqual([
      "Baixar topografia.pdf",
      "Excluir anexo topografia.pdf",
    ]);
  });

  it("shows a retryable error when the list fails", async () => {
    svc.list.mockRejectedValueOnce(httpError(500)).mockResolvedValueOnce([pdf]);
    await mountAttachments();
    expect(wrapper.find("[role='alert']").text()).toContain(
      "Não foi possível carregar os anexos"
    );
    await wrapper
      .findAll("button")
      .filter((b) => b.text() === "Tentar novamente")
      .at(0)
      .trigger("click");
    await flushPromises();
    expect(wrapper.findAll(".attachment-item")).toHaveLength(1);
  });

  it("validates type and size on the client", async () => {
    svc.list.mockResolvedValue([]);
    await mountAttachments();
    await choose(makeFile("a.docx", "application/msword", 10));
    expect(fileError()).toBe(
      "Formato não suportado. Envie um arquivo PDF, JPEG ou PNG."
    );
    expect(wrapper.find("#attachment-file").attributes("aria-invalid")).toBe(
      "true"
    );
    expect(
      wrapper.find("#attachment-file").attributes("aria-describedby")
    ).toBe("attachment-file-help attachment-file-error");
    await choose(makeFile("big.pdf", "application/pdf", 12 * 1024 * 1024));
    expect(fileError()).toBe("O arquivo tem 12 MB. O tamanho máximo é 10 MB.");

    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(svc.upload).not.toHaveBeenCalled();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Selecione um arquivo para enviar."
    );
  });

  it("asks for a file on an empty submit", async () => {
    svc.list.mockResolvedValue([]);
    await mountAttachments();
    await wrapper.find("form").trigger("submit");
    expect(fileError()).toBe("Selecione um arquivo.");
    expect(document.activeElement).toBe(
      wrapper.find("#attachment-file").element
    );
  });

  it("uploads with description and progress, prepends the item and resets", async () => {
    svc.list.mockResolvedValue([png]);
    let resolve!: (a: Attachment) => void;
    svc.upload.mockImplementation((_id, _file, _desc, options) => {
      options?.onProgress?.(65);
      return new Promise<Attachment>((r) => (resolve = r));
    });
    await mountAttachments();
    const file = makeFile("topografia.pdf", "application/pdf", 1258291);
    await choose(file);
    expect(wrapper.find("#attachment-file-selected").text()).toContain(
      "topografia.pdf"
    );
    await wrapper
      .find("#attachment-description")
      .setValue("  Topografia de córnea ");
    await wrapper.find("form").trigger("submit");
    await flushPromises();

    expect(svc.upload.mock.calls[0].slice(0, 3)).toEqual([
      12,
      file,
      "Topografia de córnea",
    ]);
    const bar = wrapper.find("[role='progressbar']");
    expect(bar.attributes("aria-valuenow")).toBe("65");
    expect(bar.attributes("aria-label")).toBe("Enviando anexo");

    resolve(pdf);
    await flushPromises();
    expect(
      wrapper.findAll(".attachment-item__name").wrappers.map((w) => w.text())
    ).toEqual(["topografia.pdf", "retina.png"]);
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      ATTACHMENT_UPLOADED
    );
    expect(wrapper.find("#attachment-file-selected").exists()).toBe(false);
    expect(
      (wrapper.find("#attachment-description").element as HTMLInputElement)
        .value
    ).toBe("");
  });

  it("accepts a dropped file and can clear the selection", async () => {
    svc.list.mockResolvedValue([]);
    await mountAttachments();
    const file = makeFile("retina.png", "image/png", 2048);
    await wrapper.find(".dropzone").trigger("dragover");
    expect(wrapper.find(".dropzone").classes()).toContain("is-dragover");
    await wrapper
      .find(".dropzone")
      .trigger("drop", { dataTransfer: { files: [file] } });
    expect(wrapper.find(".dropzone").classes()).not.toContain("is-dragover");
    expect(wrapper.find("#attachment-file-selected").text()).toContain(
      "retina.png"
    );
    expect(document.activeElement).toBe(
      wrapper.find("#attachment-file").element
    );

    await wrapper
      .findAll("button")
      .filter((b) => b.text() === "Remover arquivo selecionado")
      .at(0)
      .trigger("click");
    expect(wrapper.find("#attachment-file-selected").exists()).toBe(false);
  });

  it("maps a 422 to the fields and keeps the selection", async () => {
    svc.list.mockResolvedValue([]);
    svc.upload.mockRejectedValue(
      validationError({ file: ["O arquivo enviado não é um PDF válido."] })
    );
    await mountAttachments();
    await choose(makeFile("fake.pdf", "application/pdf", 100));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(fileError()).toBe("O arquivo enviado não é um PDF válido.");
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "O arquivo enviado não é um PDF válido."
    );
    expect(wrapper.find("#attachment-file-selected").exists()).toBe(true);
  });

  it("maps a description 422 and reports network errors inline", async () => {
    svc.list.mockResolvedValue([]);
    svc.upload
      .mockRejectedValueOnce(
        validationError({ description: ["Descrição muito longa."] })
      )
      .mockRejectedValueOnce(
        Object.assign(new Error("Network"), { isAxiosError: true })
      );
    await mountAttachments();
    await choose(makeFile("a.pdf", "application/pdf", 100));
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(wrapper.find("#attachment-description-error").text()).toBe(
      "Descrição muito longa."
    );
    expect(document.activeElement).toBe(
      wrapper.find("#attachment-description").element
    );

    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(fileError()).toContain("Não foi possível conectar ao servidor");
  });

  it("downloads through the service and saves the blob", async () => {
    svc.list.mockResolvedValue([pdf]);
    const blob = new Blob(["%PDF"]);
    svc.download.mockResolvedValueOnce({ blob, filename: "topografia.pdf" });
    await mountAttachments();
    await wrapper.find("[aria-label='Baixar topografia.pdf']").trigger("click");
    await flushPromises();
    expect(svc.download).toHaveBeenCalledWith(pdf);
    expect(saveBlob).toHaveBeenCalledWith(blob, "topografia.pdf");

    svc.download.mockRejectedValueOnce(httpError(404));
    await wrapper.find("[aria-label='Baixar topografia.pdf']").trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Anexo não encontrado."
    );
  });

  it("deletes after confirmation and moves focus to the heading", async () => {
    svc.list.mockResolvedValue([pdf, png]);
    svc.remove.mockResolvedValue();
    await mountAttachments();
    await wrapper
      .find("[aria-label='Excluir anexo topografia.pdf']")
      .trigger("click");
    await flushPromises();
    expect(document.body.textContent).toContain(
      "Deseja realmente excluir o anexo"
    );
    await clickInBody("Confirmar");
    expect(svc.remove).toHaveBeenCalledWith(3);
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      ATTACHMENT_DELETED
    );
    expect(wrapper.findAll(".attachment-item")).toHaveLength(1);
  });

  it("keeps the item when the delete fails, removes it on 404", async () => {
    svc.list.mockResolvedValue([pdf]);
    svc.remove.mockRejectedValueOnce(httpError(500));
    await mountAttachments();
    await wrapper
      .find("[aria-label='Excluir anexo topografia.pdf']")
      .trigger("click");
    await flushPromises();
    await clickInBody("Confirmar");
    expect(wrapper.findAll(".attachment-item")).toHaveLength(1);
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "O servidor encontrou um erro. Tente novamente em instantes."
    );

    svc.remove.mockRejectedValueOnce(httpError(404));
    await clickInBody("Confirmar");
    expect(wrapper.text()).toContain("Nenhum anexo");
  });
});
