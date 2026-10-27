import { beforeEach, describe, expect, it, vi } from "vitest";
import { api, multipartConfig, UPLOAD_TIMEOUT } from "../api";
import { attachmentService } from "../attachmentService";
import { cepService } from "../cepService";
import { patientService } from "../patientService";

const get = vi.spyOn(api, "get");
const post = vi.spyOn(api, "post");
const del = vi.spyOn(api, "delete");

beforeEach(() => {
  get.mockReset();
  post.mockReset();
  del.mockReset();
});

describe("multipartConfig", () => {
  it("overrides the JSON content type, raises the timeout and reports progress", () => {
    const onProgress = vi.fn();
    const config = multipartConfig({ onProgress });
    expect(config.headers["Content-Type"]).toBe("multipart/form-data");
    expect(config.timeout).toBe(UPLOAD_TIMEOUT);
    config.onUploadProgress({ loaded: 30, total: 120 });
    config.onUploadProgress({ loaded: 30 }); // unknown total: ignored
    expect(onProgress).toHaveBeenCalledTimes(1);
    expect(onProgress).toHaveBeenCalledWith(25);
    expect(() =>
      multipartConfig().onUploadProgress({ loaded: 1, total: 2 })
    ).not.toThrow();
  });
});

describe("cepService", () => {
  it("looks up the CEP by its digits, forwarding the abort signal", async () => {
    const data = {
      cep: "01310100",
      street: "Av. Paulista",
      neighborhood: "Bela Vista",
      city: "São Paulo",
      state: "SP",
    };
    get.mockResolvedValue({ data: { data } });
    const signal = new AbortController().signal;
    await expect(cepService.lookup("01310-100", { signal })).resolves.toEqual(
      data
    );
    expect(get).toHaveBeenCalledWith("/cep/01310100", { signal });
  });
});

describe("patientService photo", () => {
  it("uploads the photo as multipart FormData", async () => {
    const patient = { id: 7, photo_url: "http://api/patients/7/photo?v=2" };
    post.mockResolvedValue({ data: { data: patient } });
    const file = new File(["x"], "me.png", { type: "image/png" });
    await expect(patientService.uploadPhoto(7, file)).resolves.toEqual(patient);
    const [url, body, config] = post.mock.calls[0] as [
      string,
      FormData,
      { headers: Record<string, string> }
    ];
    expect(url).toBe("/patients/7/photo");
    expect(body).toBeInstanceOf(FormData);
    expect((body.get("photo") as File).name).toBe("me.png");
    expect(config.headers["Content-Type"]).toBe("multipart/form-data");
  });

  it("removes the photo", async () => {
    del.mockResolvedValue({});
    await patientService.removePhoto(7);
    expect(del).toHaveBeenCalledWith("/patients/7/photo");
  });
});

describe("attachmentService", () => {
  it("lists a consultation's attachments", async () => {
    get.mockResolvedValue({ data: { data: [{ id: 1 }] } });
    await expect(attachmentService.list(12)).resolves.toEqual([{ id: 1 }]);
    expect(get).toHaveBeenCalledWith("/consultations/12/attachments");
  });

  it("uploads file + description as multipart, description omitted when empty", async () => {
    post.mockResolvedValue({ data: { data: { id: 3 } } });
    const file = new File(["%PDF"], "topo.pdf", { type: "application/pdf" });
    await attachmentService.upload(12, file, "Topografia");
    const [url, body] = post.mock.calls[0] as [string, FormData];
    expect(url).toBe("/consultations/12/attachments");
    expect((body.get("file") as File).name).toBe("topo.pdf");
    expect(body.get("description")).toBe("Topografia");

    await attachmentService.upload(12, file, null);
    const [, second] = post.mock.calls[1] as [string, FormData];
    expect(second.has("description")).toBe(false);
  });

  it("downloads by id, naming the file from Content-Disposition or the original name", async () => {
    const blob = new Blob(["x"]);
    get.mockResolvedValueOnce({
      data: blob,
      headers: {
        "content-disposition": 'attachment; filename="laudo final.pdf"',
      },
    });
    await expect(
      attachmentService.download({ id: 3, original_name: "laudo.pdf" })
    ).resolves.toEqual({ blob, filename: "laudo final.pdf" });
    expect(get.mock.calls[0][0]).toBe("/attachments/3/download");
    expect(get.mock.calls[0][1]).toMatchObject({ responseType: "blob" });

    get.mockResolvedValueOnce({ data: blob, headers: {} });
    await expect(
      attachmentService.download({ id: 3, original_name: "laudo.pdf" })
    ).resolves.toEqual({ blob, filename: "laudo.pdf" });
  });

  it("deletes an attachment", async () => {
    del.mockResolvedValue({});
    await attachmentService.remove(3);
    expect(del).toHaveBeenCalledWith("/attachments/3");
  });
});
