import { readFile } from "node:fs/promises";
import type { Locator, Page, Request } from "@playwright/test";
import { addDaysIso, expect, gotoConsultation, randomFutureMonday, test, toast } from "../support/fixtures";
import type { Consultation, PatientApi } from "../support/api";
import { professionalName } from "../support/data";

const M = "−"; // diopters are shown with the typographic minus (U+2212)

/** Patient + consultation in a far-future week, moved to `completed` unless told otherwise. */
async function arrange(api: PatientApi, completed = true): Promise<Consultation> {
  const patient = await api.createPatient();
  const c = await api.createConsultation(patient.id, {
    scheduled_at: `${addDaysIso(randomFutureMonday(), 4)}T08:30:00-03:00`,
    professional_name: professionalName("Dra."),
    professional_crm: "CRM-SP 99999",
  });
  return completed ? api.setStatus(c.id, "completed") : c;
}

const f = (page: Page, key: string): Locator => page.locator(`#ee-${key}`);
const prontuario = (page: Page): Locator => page.getByRole("heading", { level: 2, name: "Prontuário" });
const saveButton = (page: Page): Locator => page.getByRole("button", { name: "Salvar prontuário" });

/** Eye-exam PUTs the page sends. */
function recordExamSaves(page: Page): Request[] {
  const sent: Request[] = [];
  page.on("request", (req) => {
    if (req.method() === "PUT" && new URL(req.url()).pathname.endsWith("/eye-exam")) sent.push(req);
  });
  return sent;
}

/** Read-only table: value of a row ("Esférico") for an eye column (1 = OD, 2 = OE). */
function examCell(page: Page, row: string, eye: "OD" | "OE"): Locator {
  return page
    .getByRole("row")
    .filter({ has: page.getByRole("rowheader", { name: row, exact: true }) })
    .getByRole("cell")
    .nth(eye === "OD" ? 0 : 1);
}

function examSaved(page: Page) {
  return page.waitForResponse(
    (res) => res.request().method() === "PUT" && new URL(res.url()).pathname.endsWith("/eye-exam")
  );
}

test.describe("Prontuário oftalmológico", () => {
  test("só aparece quando o atendimento é Realizado", async ({ page, api }) => {
    const consultation = await arrange(api, false);
    await gotoConsultation(page, consultation.id);
    await expect(page.getByRole("button", { name: "Confirmar" })).toBeVisible();
    await expect(prontuario(page)).toHaveCount(0);
    await expect(page.locator("#ee-od-spherical")).toHaveCount(0);

    await page.getByRole("button", { name: "Marcar como realizado" }).click();
    await expect(toast(page, "Atendimento marcado como realizado.")).toBeVisible();
    await expect(prontuario(page)).toBeVisible();
    await expect(page.getByRole("group", { name: "Olho direito (OD)" })).toBeVisible();
    await expect(page.getByRole("group", { name: "Olho esquerdo (OE)" })).toBeVisible();
    // Empty exam: no prescription/edit buttons yet
    await expect(page.getByRole("button", { name: "Baixar receita (PDF)" })).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Editar prontuário" })).toHaveCount(0);
  });

  test("passos de 0.25 pelos botões e pelo teclado; eixo só com cilíndrico diferente de 0", async ({
    page,
    api,
  }) => {
    const consultation = await arrange(api);
    await gotoConsultation(page, consultation.id);
    await expect(prontuario(page)).toBeVisible();

    // Buttons
    const up = page.getByRole("button", { name: "Aumentar esférico do olho direito em 0.25" });
    const down = page.getByRole("button", { name: "Diminuir esférico do olho direito em 0.25" });
    await up.click();
    await up.click();
    await expect(f(page, "od-spherical")).toHaveValue("+0.50");
    await down.click();
    await down.click();
    await expect(f(page, "od-spherical")).toHaveValue("0.00");
    await down.click();
    await expect(f(page, "od-spherical")).toHaveValue(`${M}0.25`);
    // Step buttons stay out of the tab order (arrow keys cover keyboard use)
    await expect(up).toHaveAttribute("tabindex", "-1");

    // Keyboard on the other eye
    const oeSph = f(page, "oe-spherical");
    await oeSph.focus();
    await oeSph.press("ArrowDown");
    await oeSph.press("ArrowDown");
    await oeSph.press("ArrowDown");
    await expect(oeSph).toHaveValue(`${M}0.75`);
    await oeSph.press("ArrowUp");
    await expect(oeSph).toHaveValue(`${M}0.50`);

    // Typed values are normalized on blur ("," or "." and an ASCII "-")
    await oeSph.fill("-1,5");
    await oeSph.blur();
    await expect(oeSph).toHaveValue(`${M}1.50`);
    await oeSph.fill("2");
    await oeSph.blur();
    await expect(oeSph).toHaveValue("+2.00");

    // Ranges clamp: addition starts at +0.75; cylindrical never goes above 0
    await page.getByRole("button", { name: "Aumentar adição do olho direito em 0.25" }).click();
    await expect(f(page, "od-addition")).toHaveValue("+0.75");
    await page.getByRole("button", { name: "Diminuir adição do olho direito em 0.25" }).click();
    await expect(f(page, "od-addition")).toHaveValue("+0.75");

    const axis = f(page, "od-axis");
    const cylUp = page.getByRole("button", { name: "Aumentar cilíndrico do olho direito em 0.25" });
    const cylDown = page.getByRole("button", { name: "Diminuir cilíndrico do olho direito em 0.25" });
    await expect(axis).toBeDisabled();
    await cylUp.click();
    await expect(f(page, "od-cylindrical")).toHaveValue("0.00");
    await expect(axis).toBeDisabled();
    await cylDown.click();
    await expect(f(page, "od-cylindrical")).toHaveValue(`${M}0.25`);
    await expect(axis).toBeEnabled();
    await axis.fill("90");
    // Back to 0: axis is disabled and cleared (the API forbids it without cylinder)
    await f(page, "od-cylindrical").press("ArrowUp");
    await expect(f(page, "od-cylindrical")).toHaveValue("0.00");
    await expect(axis).toBeDisabled();
    await expect(axis).toHaveValue("");
    // The other eye is independent
    await expect(f(page, "oe-axis")).toBeDisabled();
  });

  test("validação no cliente (Snellen, faixas, passos, eixo, PIO) sem enviar nada", async ({ page, api }) => {
    const consultation = await arrange(api);
    const saves = recordExamSaves(page);
    await gotoConsultation(page, consultation.id);
    await expect(prontuario(page)).toBeVisible();

    await f(page, "od-visual-acuity-sc").fill("5");
    await f(page, "od-visual-acuity-cc").fill("500");
    await f(page, "od-spherical").fill("31");
    await f(page, "oe-spherical").fill("1.1");
    await f(page, "od-cylindrical").fill("-0.5"); // axis now required
    await f(page, "oe-cylindrical").fill("-1");
    await f(page, "oe-axis").fill("200");
    await f(page, "od-addition").fill("0.5");
    await f(page, "od-iop").fill("70");
    await saveButton(page).click();

    await expect(toast(page, "Verifique os campos destacados.")).toBeVisible();
    const expected: [string, string][] = [
      ["od-visual-acuity-sc", "Use 20/N, com N entre 10 e 400"],
      ["od-visual-acuity-cc", "Use 20/N, com N entre 10 e 400"],
      ["od-spherical", `Entre ${M}30.00 e +30.00`],
      ["oe-spherical", "Use passos de 0.25"],
      ["od-axis", "Informe o eixo"],
      ["oe-axis", "Inteiro entre 0 e 180"],
      ["od-addition", "Entre +0.75 e +4.00"],
      ["od-iop", "Inteiro entre 5 e 60 mmHg"],
    ];
    for (const [key, message] of expected) {
      await expect(page.locator(`#ee-${key}-error`), key).toHaveText(message);
      await expect(f(page, key), key).toHaveAttribute("aria-describedby", new RegExp(`(^| )ee-${key}-error( |$)`));
    }
    await expect(f(page, "od-visual-acuity-sc")).toBeFocused(); // first invalid field
    expect(saves).toHaveLength(0);

    // Fixing a field clears its error
    await f(page, "od-iop").fill("14");
    await expect(page.locator("#ee-od-iop-error")).toHaveCount(0);
  });

  test("erros 422 do servidor aparecem nos campos (chaves com ponto)", async ({ page, api }) => {
    const consultation = await arrange(api);
    await gotoConsultation(page, consultation.id);
    await expect(prontuario(page)).toBeVisible();

    // The client accepts these; the request is rewritten on its way so the real API rejects it.
    await page.route("**/eye-exam", async (route) => {
      const body = route.request().postDataJSON();
      body.od.visual_acuity_cc = "20/040"; // no leading zero
      body.oe.iop = 99;
      await route.continue({ postData: JSON.stringify(body) });
    });
    await f(page, "od-visual-acuity-cc").fill("40");
    await f(page, "oe-iop").fill("15");
    const saved = examSaved(page);
    await saveButton(page).click();
    const response = await saved;
    expect(response.status()).toBe(422);
    const errors = (await response.json()).errors;
    expect(Object.keys(errors).sort()).toEqual(["od.visual_acuity_cc", "oe.iop"]);

    await expect(toast(page, "Verifique os campos destacados para salvar!")).toBeVisible();
    await expect(page.locator("#ee-od-visual-acuity-cc-error")).toHaveText(errors["od.visual_acuity_cc"][0]);
    await expect(page.locator("#ee-oe-iop-error")).toHaveText(errors["oe.iop"][0]);
    await expect(f(page, "od-visual-acuity-cc")).toHaveClass(/is-invalid/);
    await expect(f(page, "od-visual-acuity-cc")).toBeFocused();
    // Still the form (nothing saved)
    await expect(saveButton(page)).toBeVisible();
    expect((await (await api.getConsultation(consultation.id)).json()).data.eye_exam).toBeNull();
  });

  test("salva, mostra a visualização somente leitura e permite editar", async ({ page, api }) => {
    const consultation = await arrange(api);
    await gotoConsultation(page, consultation.id);
    await expect(prontuario(page)).toBeVisible();

    await f(page, "od-visual-acuity-sc").fill("40");
    await f(page, "od-visual-acuity-cc").fill("20");
    await f(page, "od-spherical").fill("-1.25");
    await f(page, "od-cylindrical").fill("-0.5");
    await f(page, "od-axis").fill("180");
    await f(page, "od-iop").fill("14");
    await f(page, "oe-spherical").fill("-1");
    await f(page, "oe-addition").fill("2");
    await f(page, "oe-iop").fill("15");
    await page.locator("#ee-diagnosis").fill("Miopia leve com astigmatismo.");
    await page.locator("#ee-observations").fill("Retorno em 1 ano.");

    const saved = examSaved(page);
    await saveButton(page).click();
    const response = await saved;
    expect(response.status()).toBe(200);
    expect(response.request().postDataJSON()).toEqual({
      od: {
        visual_acuity_sc: "20/40",
        visual_acuity_cc: "20/20",
        spherical: -1.25,
        cylindrical: -0.5,
        axis: 180,
        addition: null,
        iop: 14,
      },
      oe: {
        visual_acuity_sc: null,
        visual_acuity_cc: null,
        spherical: -1,
        cylindrical: null,
        axis: null,
        addition: 2,
        iop: 15,
      },
      diagnosis: "Miopia leve com astigmatismo.",
      observations: "Retorno em 1 ano.",
    });
    await expect(toast(page, "Prontuário salvo com sucesso!")).toBeVisible();

    // Read-only view
    await expect(saveButton(page)).toHaveCount(0);
    await expect(page.locator("#prontuario-title")).toBeFocused();
    await expect(examCell(page, "Acuidade sem correção", "OD")).toHaveText("20/40");
    await expect(examCell(page, "Acuidade com correção", "OD")).toHaveText("20/20");
    await expect(examCell(page, "Esférico", "OD")).toHaveText(`${M}1.25`);
    await expect(examCell(page, "Cilíndrico", "OD")).toHaveText(`${M}0.50`);
    await expect(examCell(page, "Eixo", "OD")).toHaveText("180°");
    await expect(examCell(page, "PIO", "OD")).toHaveText("14 mmHg");
    await expect(examCell(page, "Esférico", "OE")).toHaveText(`${M}1.00`);
    await expect(examCell(page, "Cilíndrico", "OE")).toHaveText("—");
    await expect(examCell(page, "Adição", "OE")).toHaveText("+2.00");
    await expect(page.getByText("Miopia leve com astigmatismo.", { exact: true })).toBeVisible();
    await expect(page.getByText("Retorno em 1 ano.", { exact: true })).toBeVisible();
    await expect(page.getByText(/^\s*Atualizado em \d{2}\/\d{2}\/\d{4} às \d{2}:\d{2}\s*$/)).toBeVisible();

    // Edit: prefilled; "Cancelar" goes back without saving
    await page.getByRole("button", { name: "Editar prontuário" }).click();
    await expect(f(page, "od-spherical")).toHaveValue(`${M}1.25`);
    await expect(f(page, "od-visual-acuity-sc")).toHaveValue("40");
    await expect(f(page, "od-axis")).toHaveValue("180");
    await expect(f(page, "oe-addition")).toHaveValue("+2.00");
    await page.getByRole("button", { name: "Aumentar esférico do olho direito em 0.25" }).click();
    await page.getByRole("button", { name: "Cancelar" }).click();
    await expect(examCell(page, "Esférico", "OD")).toHaveText(`${M}1.25`);

    await page.getByRole("button", { name: "Editar prontuário" }).click();
    await page.getByRole("button", { name: "Aumentar esférico do olho direito em 0.25" }).click();
    await expect(f(page, "od-spherical")).toHaveValue(`${M}1.00`);
    await page.locator("#ee-diagnosis").fill("Miopia leve.");
    await saveButton(page).click();
    await expect(toast(page, "Prontuário salvo com sucesso!")).toBeVisible();
    await expect(examCell(page, "Esférico", "OD")).toHaveText(`${M}1.00`);
    await expect(page.getByText("Miopia leve.", { exact: true })).toBeVisible();

    // Persisted (survives a reload)
    await page.reload();
    await expect(examCell(page, "Esférico", "OD")).toHaveText(`${M}1.00`);
    await expect(examCell(page, "Eixo", "OD")).toHaveText("180°");
  });

  test("Baixar receita (PDF): download com nome receita-<id>-AAAAMMDD.pdf", async ({ page, api }) => {
    test.slow(); // the first PDF render can be slow on a cold CI container
    const consultation = await arrange(api);
    await api.saveEyeExam(consultation.id, {
      od: { spherical: -1.25, cylindrical: -0.5, axis: 180 },
      oe: { spherical: -1 },
    });
    await gotoConsultation(page, consultation.id);
    const expectedName = `receita-${consultation.id}-${consultation.scheduled_at.slice(0, 10).replace(/-/g, "")}.pdf`;

    const download = page.waitForEvent("download");
    await page.getByRole("button", { name: "Baixar receita (PDF)" }).click();
    const file = await download;
    expect(file.suggestedFilename()).toBe(expectedName);
    const bytes = await readFile(await file.path());
    expect(bytes.subarray(0, 5).toString("latin1")).toBe("%PDF-");
    await expect(toast(page, `Receita baixada: ${expectedName}`)).toBeVisible();
    await expect(page.getByRole("button", { name: "Baixar receita (PDF)" })).toBeEnabled();
  });

  test("Baixar receita sem refração mostra o erro 422 da API", async ({ page, api }) => {
    const consultation = await arrange(api);
    await api.saveEyeExam(consultation.id, { diagnosis: "Sem queixas refrativas." });
    await gotoConsultation(page, consultation.id);

    let downloads = 0;
    page.on("download", () => downloads++);
    const answered = page.waitForResponse((res) => new URL(res.url()).pathname.endsWith("/prescription"));
    await page.getByRole("button", { name: "Baixar receita (PDF)" }).click();
    expect((await answered).status()).toBe(422);
    await expect(toast(page, "O atendimento não tem dados de refração para gerar a receita.")).toBeVisible();
    expect(downloads).toBe(0);
  });
});
