import { readFile } from "node:fs/promises";
import type { Locator, Page, Request } from "@playwright/test";
import {
  addDaysIso,
  expect,
  formatDateTimeSaoPaulo,
  gotoConsultation,
  randomFutureMonday,
  test,
  toast,
  waitForFadeIn,
} from "../support/fixtures";
import type { Attachment, Consultation, PatientApi, UploadFile } from "../support/api";
import { professionalName } from "../support/data";
import { bigFile, examPdf, notAnImage, photoPng } from "../support/files";

/** Name with characters the server strips (":" and "?"), plus a non-ASCII one kept in filename*. */
const RAW_PDF_NAME = "Laudo: córnea?.pdf";
const PDF_NAME = "Laudo córnea.pdf";

async function createConsultation(api: PatientApi): Promise<Consultation> {
  const patient = await api.createPatient();
  return api.createConsultation(patient.id, {
    scheduled_at: `${addDaysIso(randomFutureMonday(), 2)}T09:00:00-03:00`,
    professional_name: professionalName(),
  });
}

const section = (page: Page) => page.getByRole("region", { name: "Anexos" });
const fileInput = (page: Page) => page.locator("#attachment-file");
const fileError = (page: Page) => page.locator("#attachment-file-error");
const items = (page: Page) => page.locator(".attachment-item");
const itemFor = (page: Page, name: string) =>
  items(page).filter({ has: page.locator(".attachment-item__name", { hasText: new RegExp(`^${name}$`) }) });
const emptyState = (page: Page) => section(page).getByText("Nenhum anexo", { exact: true });
const deleteDialog = (page: Page) => page.getByRole("dialog", { name: "Excluir anexo" });

async function gotoAttachments(page: Page, consultation: Consultation): Promise<void> {
  await gotoConsultation(page, consultation.id);
  await expect(page.getByRole("heading", { level: 2, name: "Anexos" })).toBeVisible();
  await expect(section(page).getByText("Carregando anexos...")).toHaveCount(0);
}

/** Clicks "Enviar anexo" and returns the API response. */
async function submit(page: Page, consultation: Consultation, status = 201): Promise<any> {
  const response = page.waitForResponse(
    (res) =>
      res.request().method() === "POST" &&
      new URL(res.url()).pathname.endsWith(`/consultations/${consultation.id}/attachments`)
  );
  await page.getByRole("button", { name: "Enviar anexo" }).click();
  const res = await response;
  expect(res.status()).toBe(status);
  return res.json();
}

async function expectMeta(item: Locator, a: Attachment, size: string): Promise<void> {
  await expect(item.locator(".attachment-item__meta")).toContainText(size);
  await expect(item.locator(".attachment-item__meta time")).toHaveText(formatDateTimeSaoPaulo(a.created_at));
  await expect(item.locator(".attachment-item__meta time")).toHaveAttribute("datetime", a.created_at);
}

function collectUploads(page: Page): Request[] {
  const posts: Request[] = [];
  page.on("request", (req) => {
    if (req.method() === "POST" && /\/attachments$/.test(new URL(req.url()).pathname)) posts.push(req);
  });
  return posts;
}

test.describe("Anexos do atendimento", () => {
  test("enviar PDF (com descrição) e PNG: lista com nome saneado, descrição, tamanho e data, mais recente primeiro", async ({
    page,
    api,
  }) => {
    const consultation = await createConsultation(api);
    await gotoAttachments(page, consultation);
    await expect(emptyState(page)).toBeVisible();
    await expect(page.getByText("Selecionar arquivo ou arraste e solte aqui")).toBeVisible();
    // Two labels (the field label and the drop zone that wraps the input): "Arquivo Selecionar arquivo ...".
    await expect(fileInput(page)).toHaveAccessibleName(/^Arquivo\b/);
    await expect(fileInput(page)).toHaveAccessibleDescription("PDF, JPEG ou PNG, até 10 MB.");

    // PDF with a description; the upload is held a moment so the progress bar can be seen.
    await page.route(`**/api/consultations/${consultation.id}/attachments`, async (route) => {
      if (route.request().method() === "POST") await new Promise((r) => setTimeout(r, 400));
      await route.fallback();
    });
    await fileInput(page).setInputFiles(examPdf(RAW_PDF_NAME));
    await expect(page.locator("#attachment-file-selected")).toContainText(RAW_PDF_NAME);
    await page.getByRole("textbox", { name: "Descrição (Opcional)" }).fill("Topografia de córnea");
    const responsePromise = submit(page, consultation);
    await expect(section(page).getByRole("progressbar", { name: "Enviando anexo" })).toBeVisible();
    const pdf = (await responsePromise).data as Attachment;
    expect(pdf).toMatchObject({
      consultation_id: consultation.id,
      original_name: PDF_NAME,
      mime_type: "application/pdf",
      size: examPdf().buffer.length,
      description: "Topografia de córnea",
    });
    await expect(toast(page, "Anexo enviado com sucesso!")).toBeVisible();
    await expect(emptyState(page)).toHaveCount(0);
    // The form is reset for the next file.
    await expect(page.getByRole("textbox", { name: "Descrição (Opcional)" })).toHaveValue("");
    await expect(page.locator("#attachment-file-selected")).toHaveCount(0);

    const pdfItem = itemFor(page, PDF_NAME);
    await expect(pdfItem.locator(".attachment-item__description")).toHaveText("Topografia de córnea");
    await expectMeta(pdfItem, pdf, `${examPdf().buffer.length} B`);
    await expect(pdfItem.getByRole("button", { name: `Baixar ${PDF_NAME}` })).toBeVisible();
    await expect(pdfItem.getByRole("button", { name: `Excluir anexo ${PDF_NAME}` })).toBeVisible();

    // PNG without a description goes to the top.
    await fileInput(page).setInputFiles(photoPng("retina.png"));
    const png = (await submit(page, consultation)).data as Attachment;
    expect(png).toMatchObject({ original_name: "retina.png", mime_type: "image/png", description: null });
    await expect(items(page)).toHaveCount(2);
    await expect(items(page).locator(".attachment-item__name")).toHaveText(["retina.png", PDF_NAME]);
    await expect(itemFor(page, "retina.png").locator(".attachment-item__description")).toHaveCount(0);
    await expectMeta(itemFor(page, "retina.png"), png, `${photoPng().buffer.length} B`);

    // Same order after a reload (from GET .../attachments).
    await page.reload();
    await expect(items(page).locator(".attachment-item__name")).toHaveText(["retina.png", PDF_NAME]);
    expect((await api.attachments(consultation.id)).map((a) => a.id)).toEqual([png.id, pdf.id]);
  });

  test("baixar: nome do arquivo (Content-Disposition) e bytes iguais ao original", async ({ page, api }) => {
    const consultation = await createConsultation(api);
    const pdf = await api.uploadAttachment(consultation.id, examPdf(RAW_PDF_NAME), "Laudo");
    const png = await api.uploadAttachment(consultation.id, photoPng("retina.png"));
    expect(pdf.original_name).toBe(PDF_NAME);
    await gotoAttachments(page, consultation);

    for (const [attachment, original] of [
      [pdf, examPdf()],
      [png, photoPng()],
    ] as const) {
      const response = page.waitForResponse(
        (res) => new URL(res.url()).pathname.endsWith(`/attachments/${attachment.id}/download`)
      );
      const download = page.waitForEvent("download");
      await page.getByRole("button", { name: `Baixar ${attachment.original_name}` }).click();
      const res = await response;
      expect(res.status()).toBe(200);
      expect(res.headers()["content-disposition"]).toContain("attachment;");
      // Without this the SPA could not read the name (it would fall back to original_name).
      expect((res.headers()["access-control-expose-headers"] ?? "").toLowerCase()).toContain("content-disposition");
      const file = await download;
      expect(file.suggestedFilename()).toBe(attachment.original_name);
      const bytes = await readFile(await file.path());
      expect(bytes.equals(original.buffer)).toBe(true);
    }
    // The non-ASCII name comes from filename* (the ASCII fallback would be "Laudo cornea.pdf").
    const header = (await api.raw("get", `attachments/${pdf.id}/download`, { headers: { Accept: "*/*" } })).headers()[
      "content-disposition"
    ];
    expect(header).toContain('filename="Laudo cornea.pdf"');
    expect(header).toContain(`filename*=UTF-8''${encodeURIComponent(PDF_NAME)}`);
  });

  test("erros no cliente: sem arquivo, formato não suportado e arquivo maior que 10 MB (nada é enviado)", async ({
    page,
    api,
  }) => {
    const consultation = await createConsultation(api);
    const posts = collectUploads(page);
    await gotoAttachments(page, consultation);

    await page.getByRole("button", { name: "Enviar anexo" }).click();
    await expect(fileError(page)).toHaveText("Selecione um arquivo.");
    await expect(toast(page, "Selecione um arquivo para enviar.")).toBeVisible();
    await expect(fileInput(page)).toBeFocused();

    await fileInput(page).setInputFiles({ name: "notas.txt", mimeType: "text/plain", buffer: Buffer.from("texto") });
    await expect(fileError(page)).toHaveText("Formato não suportado. Envie um arquivo PDF, JPEG ou PNG.");
    await expect(fileInput(page)).toHaveAttribute("aria-invalid", "true");
    await expect(fileInput(page)).toHaveAccessibleDescription(
      /Formato não suportado\. Envie um arquivo PDF, JPEG ou PNG\./
    );

    await fileInput(page).setInputFiles(bigFile("grande.pdf", "application/pdf", Math.round(10.5 * 1024 * 1024)));
    await expect(fileError(page)).toHaveText("O arquivo tem 10,5 MB. O tamanho máximo é 10 MB.");
    await expect(page.locator("#attachment-file-selected")).toHaveCount(0);

    await page.getByRole("button", { name: "Enviar anexo" }).click();
    await expect(fileError(page)).toHaveText("O arquivo tem 10,5 MB. O tamanho máximo é 10 MB.");
    expect(posts).toHaveLength(0);
    await expect(emptyState(page)).toBeVisible();
  });

  test("erro 422 do servidor: texto renomeado para .pdf é rejeitado pelo conteúdo", async ({ page, api }) => {
    const consultation = await createConsultation(api);
    await gotoAttachments(page, consultation);

    await fileInput(page).setInputFiles(notAnImage("falso.pdf", "application/pdf"));
    await expect(page.locator("#attachment-file-selected")).toContainText("falso.pdf");
    const body = await submit(page, consultation, 422);
    expect(body.errors.file).toEqual(["O arquivo deve ser um PDF, JPEG ou PNG."]);
    await expect(fileError(page)).toHaveText("O arquivo deve ser um PDF, JPEG ou PNG.");
    await expect(toast(page, "O arquivo deve ser um PDF, JPEG ou PNG.")).toBeVisible();
    await expect(fileInput(page)).toBeFocused();
    await expect(emptyState(page)).toBeVisible();
    expect(await api.attachments(consultation.id)).toEqual([]);
  });

  test("excluir: modal com Cancelar e Confirmar, volta ao estado vazio", async ({ page, api }) => {
    const consultation = await createConsultation(api);
    const pdf = await api.uploadAttachment(consultation.id, examPdf("exame.pdf"));
    await gotoAttachments(page, consultation);
    await expect(items(page)).toHaveCount(1);

    const deleteButton = page.getByRole("button", { name: "Excluir anexo exame.pdf" });
    await deleteButton.click();
    const dialog = deleteDialog(page);
    await waitForFadeIn(dialog);
    await expect(dialog).toContainText("Deseja realmente excluir o anexo exame.pdf? Esta ação não pode ser desfeita.");
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(items(page)).toHaveCount(1);
    await expect(deleteButton).toBeFocused();

    await deleteButton.click();
    await waitForFadeIn(dialog);
    const deleted = page.waitForResponse(
      (res) => res.request().method() === "DELETE" && new URL(res.url()).pathname.endsWith(`/attachments/${pdf.id}`)
    );
    await dialog.getByRole("button", { name: "Confirmar" }).click();
    expect((await deleted).status()).toBe(204);
    await expect(toast(page, "Anexo excluído com sucesso!")).toBeVisible();
    await expect(dialog).toBeHidden();
    await expect(items(page)).toHaveCount(0);
    await expect(emptyState(page)).toBeVisible();
    await expect(page.locator("#anexos-title")).toBeFocused();

    expect(await api.attachments(consultation.id)).toEqual([]);
    const gone = await api.raw("get", `attachments/${pdf.id}/download`);
    expect(gone.status()).toBe(404);
    expect((await gone.json()).message).toBe("Anexo não encontrado.");
  });
});
