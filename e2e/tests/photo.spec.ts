import type { Locator, Page, Request } from "@playwright/test";
import {
  detailValue,
  expect,
  gotoListing,
  historyChange,
  historyEntries,
  rowFor,
  test,
  toast,
  waitForFadeIn,
  waitForHistory,
} from "../support/fixtures";
import type { Patient, UploadFile } from "../support/api";
import { uniqueTag } from "../support/data";
import { bigFile, exifJpeg, jpegInfo, notAnImage, photoPng, tinyPng, widePng } from "../support/files";

const PHOTO_URL = /\/api\/patients\/(\d+)\/photo\?v=(\d+)$/;

const pickButton = (page: Page) => page.locator("#patient-photo-pick");
const previewDialog = (page: Page) => page.getByRole("dialog", { name: "Enviar foto" });
const removeDialog = (page: Page) => page.getByRole("dialog", { name: "Remover foto" });
const photoImg = (page: Page, patient: Pick<Patient, "name">) =>
  page.getByRole("img", { name: `Foto de ${patient.name}`, exact: true });

async function gotoDetail(page: Page, patient: Patient): Promise<void> {
  await page.goto(`/pacientes/${patient.id}`);
  await expect(detailValue(page, "Nome completo")).toHaveText(patient.name);
}

/** "Adicionar foto"/"Trocar foto" opens the native picker: answer it with `file`. */
async function choosePhoto(page: Page, file: UploadFile): Promise<void> {
  const chooser = page.waitForEvent("filechooser");
  await pickButton(page).click();
  await (await chooser).setFiles(file);
}

/** The photo was actually decoded by the browser (a cross-origin <img> blocked by CORP/CORS would not). */
async function expectImageLoaded(img: Locator): Promise<void> {
  await expect
    .poll(() => img.evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0))
    .toBe(true);
}

/** Sends the chosen photo from the preview modal; returns the API response body. */
async function confirmUpload(page: Page, patient: Patient, status = 200): Promise<any> {
  const dialog = previewDialog(page);
  await waitForFadeIn(dialog);
  const response = page.waitForResponse(
    (res) => res.request().method() === "POST" && new URL(res.url()).pathname.endsWith(`/patients/${patient.id}/photo`)
  );
  await dialog.getByRole("button", { name: "Enviar foto" }).click();
  const res = await response;
  expect(res.status()).toBe(status);
  return res.json();
}

function collectPhotoPosts(page: Page): Request[] {
  const posts: Request[] = [];
  page.on("request", (req) => {
    if (req.method() === "POST" && /\/photo$/.test(new URL(req.url()).pathname)) posts.push(req);
  });
  return posts;
}

const hasBytes = (haystack: Buffer, needle: string) => haystack.includes(Buffer.from(needle, "latin1"));

test.describe("Foto do paciente", () => {
  test("enviar pela pré-visualização, foto servida reprocessada (JPEG ≤ 512, sem EXIF) e Trocar foto com ?v novo", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    await gotoDetail(page, patient);
    const avatar = page.locator(".patient-detail__header .patient-avatar");
    // Without a photo: initials, hidden from assistive tech.
    await expect(avatar).toHaveAttribute("aria-hidden", "true");
    await expect(avatar.locator("img")).toHaveCount(0);
    await expect(pickButton(page)).toHaveText("Adicionar foto");
    await expect(pickButton(page)).toHaveAccessibleDescription("JPEG, PNG ou WebP, até 2 MB.");
    await expect(page.locator("#patient-photo-remove")).toHaveCount(0);

    // The fixture really carries EXIF with GPS (so the check below is not vacuous).
    const original = exifJpeg();
    expect(hasBytes(original.buffer, "Exif\0\0")).toBe(true);
    expect(hasBytes(original.buffer, "E2ECam")).toBe(true);
    expect(jpegInfo(original.buffer).markers).toContain(0xe1);

    await choosePhoto(page, original);
    const dialog = previewDialog(page);
    await expect(dialog.getByRole("img", { name: "Pré-visualização da nova foto" })).toBeVisible();
    await expect(dialog.getByText("exif-gps.jpg")).toBeVisible();
    await expect(dialog.getByText("A imagem será recortada no centro em formato quadrado.")).toBeVisible();
    const first = (await confirmUpload(page, patient)).data as Patient;
    await expect(toast(page, "Foto atualizada com sucesso!")).toBeVisible();
    await expect(dialog).toBeHidden();

    const [, id, v1] = PHOTO_URL.exec(first.photo_url ?? "") ?? [];
    expect(Number(id)).toBe(patient.id);
    const img = photoImg(page, patient);
    await expect(img).toHaveAttribute("src", first.photo_url!);
    await expectImageLoaded(img);
    await expect(avatar).not.toHaveAttribute("aria-hidden", "true");
    await expect(pickButton(page)).toHaveText("Trocar foto");
    await expect(page.locator("#patient-photo-remove")).toHaveText("Remover foto");

    // Served bytes: JPEG, square, at most 512 px, and no EXIF/APP1 left (no camera make, no GPS).
    const served = await api.getUrl(first.photo_url!);
    expect(served.status()).toBe(200);
    expect(served.headers()["content-type"]).toBe("image/jpeg");
    expect(served.headers()["cache-control"]).toContain("private");
    const bytes = await served.body();
    const info = jpegInfo(bytes);
    expect(info.width).toBe(info.height);
    expect(info.width).toBeLessThanOrEqual(512);
    expect(info.markers).not.toContain(0xe1);
    expect(hasBytes(bytes, "Exif")).toBe(false);
    expect(hasBytes(bytes, "E2ECam")).toBe(false);

    // Trocar foto: a non-square 900x600 PNG becomes a 512x512 JPEG under a new version.
    await page.route(`**/api/patients/${patient.id}/photo`, async (route) => {
      // Hold the upload a moment so the progress bar can be seen.
      if (route.request().method() === "POST") await new Promise((r) => setTimeout(r, 400));
      await route.fallback();
    });
    await choosePhoto(page, widePng());
    await waitForFadeIn(previewDialog(page));
    const response = page.waitForResponse(
      (res) => res.request().method() === "POST" && new URL(res.url()).pathname.endsWith(`/patients/${patient.id}/photo`)
    );
    await previewDialog(page).getByRole("button", { name: "Enviar foto" }).click();
    await expect(previewDialog(page).getByRole("progressbar", { name: "Enviando foto" })).toBeVisible();
    await expect(previewDialog(page).getByRole("button", { name: "Cancelar" })).toBeDisabled();
    const second = (await (await response).json()).data as Patient;
    await expect(toast(page, "Foto atualizada com sucesso!").first()).toBeVisible();

    const [, , v2] = PHOTO_URL.exec(second.photo_url ?? "") ?? [];
    expect(Number(v2)).toBe(Number(v1) + 1);
    await expect(img).toHaveAttribute("src", second.photo_url!);
    await expectImageLoaded(img);
    const wide = jpegInfo(await (await api.getUrl(second.photo_url!)).body());
    expect([wide.width, wide.height]).toEqual([512, 512]);
  });

  test("erros no cliente: formato não suportado e imagem maior que 2 MB (nada é enviado)", async ({ page, api }) => {
    const patient = await api.createPatient();
    const posts = collectPhotoPosts(page);
    await gotoDetail(page, patient);
    const error = page.locator("#patient-photo-error");

    await choosePhoto(page, { name: "laudo.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.4") });
    await expect(error).toHaveText("Formato não suportado. Envie uma imagem JPEG, PNG ou WebP.");
    await expect(error).toHaveAttribute("role", "alert");
    await expect(previewDialog(page)).toHaveCount(0);

    await choosePhoto(page, bigFile("grande.jpg", "image/jpeg", Math.round(2.5 * 1024 * 1024)));
    await expect(error).toHaveText("A imagem tem 2,5 MB. O tamanho máximo é 2 MB.");
    await expect(previewDialog(page)).toHaveCount(0);

    // A valid file clears the error.
    await choosePhoto(page, photoPng());
    await waitForFadeIn(previewDialog(page));
    await expect(error).toHaveCount(0);
    await previewDialog(page).getByRole("button", { name: "Cancelar" }).click();
    await expect(previewDialog(page)).toBeHidden();
    await expect(pickButton(page)).toBeFocused();
    await expect(pickButton(page)).toHaveText("Adicionar foto");
    expect(posts).toHaveLength(0);
  });

  test("erro 422 do servidor (dimensões mínimas) aparece no modal; conteúdo que não é imagem é rejeitado", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    await gotoDetail(page, patient);

    await choosePhoto(page, tinyPng());
    const body = await confirmUpload(page, patient, 422);
    expect(body.errors.photo).toEqual(["A foto deve ter entre 100x100 e 4000x4000 pixels."]);
    const dialog = previewDialog(page);
    await expect(dialog.getByRole("alert")).toHaveText("A foto deve ter entre 100x100 e 4000x4000 pixels.");
    await expect(dialog.getByRole("button", { name: "Enviar foto" })).toBeEnabled();
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole("img", { name: /^Foto de / })).toHaveCount(0);

    // Text renamed to .jpg passes the client check (type from the extension) but not the server's.
    const res = await api.uploadPhotoRaw(patient.id, notAnImage());
    expect(res.status()).toBe(422);
    expect((await res.json()).errors.photo).toEqual(["A foto deve ser uma imagem JPEG, PNG ou WebP."]);
    expect((await (await api.getPatient(patient.id)).json()).data.photo_url).toBeNull();
  });

  test("remover: modal com Cancelar e Confirmar; histórico registra Foto: Adicionada, Alterada e Removida", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    await api.uploadPhoto(patient.id, photoPng());
    const withPhoto = await api.uploadPhoto(patient.id, widePng());
    await gotoDetail(page, patient);
    await expectImageLoaded(photoImg(page, patient));

    const remove = page.locator("#patient-photo-remove");
    await remove.click();
    const dialog = removeDialog(page);
    await waitForFadeIn(dialog);
    await expect(dialog).toContainText(`Deseja realmente remover a foto de ${patient.name}?`);
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(photoImg(page, patient)).toBeVisible();
    expect((await api.getUrl(withPhoto.photo_url!)).status()).toBe(200);

    await remove.click();
    await waitForFadeIn(dialog);
    const deleted = page.waitForResponse(
      (res) => res.request().method() === "DELETE" && new URL(res.url()).pathname.endsWith(`/patients/${patient.id}/photo`)
    );
    await dialog.getByRole("button", { name: "Confirmar" }).click();
    expect((await deleted).status()).toBe(204);
    await expect(toast(page, "Foto removida com sucesso!")).toBeVisible();
    await expect(dialog).toBeHidden();
    await expect(photoImg(page, patient)).toHaveCount(0);
    await expect(page.locator(".patient-detail__header .patient-avatar")).toHaveAttribute("aria-hidden", "true");
    await expect(pickButton(page)).toHaveText("Adicionar foto");
    await expect(remove).toHaveCount(0);
    expect((await api.getUrl(withPhoto.photo_url!)).status()).toBe(404);
    expect((await (await api.getPatient(patient.id)).json()).data.photo_url).toBeNull();

    await page.getByRole("tab", { name: "Histórico" }).click();
    await waitForHistory(page);
    // Newest first: removed, changed, added, created. The image never enters the history.
    await expect(historyEntries(page)).toHaveCount(4);
    const summaries = ["Removida", "Alterada", "Adicionada"];
    for (const [i, summary] of summaries.entries()) {
      const entry = historyEntries(page).nth(i);
      await expect(entry.locator(".history-item__title strong")).toHaveText("Edição");
      const change = historyChange(entry, "Foto");
      await expect(change.locator(".history-change__summary")).toHaveText(summary);
      await expect(change).toContainText(`Foto: ${summary}`);
    }
  });

  test("listagem: avatar com foto é decorativo e o texto da célula do nome não muda", async ({ page, api }) => {
    const prefix = uniqueTag();
    const withPhoto = await api.createPatient({ name: `${prefix} aa Com Foto` });
    const without = await api.createPatient({ name: `${prefix} ab Sem Foto` });
    await api.uploadPhoto(withPhoto.id, photoPng());

    await gotoListing(page, prefix);
    const cellWith = rowFor(page, withPhoto.name).getByRole("cell", { name: withPhoto.name, exact: true });
    await expect(cellWith).toHaveText(withPhoto.name);
    const avatar = cellWith.locator(".patient-avatar");
    await expect(avatar).toHaveAttribute("aria-hidden", "true");
    const img = avatar.locator("img");
    await expect(img).toHaveAttribute("alt", "");
    await expect(img).toHaveAttribute("src", PHOTO_URL);
    await expectImageLoaded(img);

    const cellWithout = rowFor(page, without.name).getByRole("cell", { name: without.name, exact: true });
    await expect(cellWithout).toHaveText(without.name);
    await expect(cellWithout.locator(".patient-avatar")).toHaveAttribute("data-initials", "EF");
    await expect(cellWithout.locator(".patient-avatar img")).toHaveCount(0);
  });
});
