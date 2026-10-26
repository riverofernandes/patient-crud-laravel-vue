import AxeBuilder from "@axe-core/playwright";
import type { Locator, Page } from "@playwright/test";
import { deleteDialog, detailValue, expect, field, gotoListing, rowFor, test } from "../support/fixtures";
import type { Patient } from "../support/api";

const WCAG_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"];

/** Runs axe on the current page and fails with a readable list of violations. */
async function expectNoA11yViolations(page: Page): Promise<void> {
  const { violations } = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
  const summary = violations.map(
    (v) => `${v.id} (${v.impact}): ${v.help}\n  ${v.nodes.map((n) => n.target.join(" ")).join("\n  ")}`
  );
  expect(summary, "axe violations").toEqual([]);
}

/** Waits for Bootstrap's fade-in to finish so color-contrast is measured at full opacity. */
async function waitForFadeIn(locator: Locator): Promise<void> {
  await expect(locator).toBeVisible();
  await expect
    .poll(() => locator.evaluate((el) => getComputedStyle(el.closest(".modal") ?? el).opacity))
    .toBe("1");
}

for (const viewport of [
  { name: "desktop", width: 1280, height: 900 },
  { name: "mobile", width: 375, height: 812 },
]) {
  test.describe(`Acessibilidade (axe, WCAG 2.1 AA) @ ${viewport.name}`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    let patient: Patient;

    test.beforeEach(async ({ api }) => {
      patient = await api.createPatient({ email: "e2e@example.com", description: "Paciente criado pelo E2E" });
    });

    test("listagem", async ({ page }) => {
      await gotoListing(page);
      await expect(page.getByRole("table", { name: "Lista de pacientes" }).getByRole("row").nth(1)).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("detalhes", async ({ page }) => {
      await page.goto(`/pacientes/${patient.id}`);
      await expect(detailValue(page, "Nome completo")).toHaveText(patient.name);
      await expectNoA11yViolations(page);
    });

    test("cadastro", async ({ page }) => {
      await page.goto("/pacientes/novo");
      await expect(page.getByRole("heading", { level: 1, name: "Cadastro de novo paciente" })).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("cadastro com erros", async ({ page }) => {
      await page.goto("/pacientes/novo");
      await page.getByRole("button", { name: "Salvar paciente" }).click();
      await expect(field(page, "Nome completo")).toHaveAccessibleDescription("Campo obrigatório");
      await expectNoA11yViolations(page);
    });

    test("edição", async ({ page }) => {
      await page.goto(`/pacientes/${patient.id}/editar`);
      await expect(field(page, "Nome completo")).toHaveValue(patient.name);
      await expectNoA11yViolations(page);
    });

    test("modal de exclusão", async ({ page }) => {
      await gotoListing(page, patient.name);
      await rowFor(page, patient.name).getByRole("button", { name: `Excluir ${patient.name}` }).click();
      await waitForFadeIn(deleteDialog(page));
      await expectNoA11yViolations(page);
    });

    test("paciente não encontrado", async ({ page, api }) => {
      await api.deletePatient(patient.id);
      await page.goto(`/pacientes/${patient.id}`);
      await expect(page.getByRole("heading", { level: 1, name: "Paciente não encontrado" })).toBeVisible();
      await expectNoA11yViolations(page);
    });
  });
}
