import type { Page } from "@playwright/test";
import { deleteDialog, emptyState, expect, gotoListing, rowFor, test, toast } from "../support/fixtures";
import type { Patient } from "../support/api";

async function expectNotFound(page: Page, patient: Patient): Promise<void> {
  await page.goto(`/pacientes/${patient.id}`);
  await expect(page.getByText("Paciente não encontrado", { exact: true })).toBeVisible();
  await page.goto(`/pacientes/${patient.id}/editar`);
  await expect(page.getByText("Paciente não encontrado", { exact: true })).toBeVisible();
}

test.describe("Exclusão de paciente", () => {
  let patient: Patient;

  test.beforeEach(async ({ api }) => {
    patient = await api.createPatient();
  });

  test("pela listagem: cancelar mantém, confirmar remove", async ({ page, api }) => {
    await gotoListing(page, patient.name);
    const row = rowFor(page, patient.name);
    const dialog = deleteDialog(page);

    await row.getByRole("button", { name: `Excluir ${patient.name}` }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(
      "Atenção! Ao excluir este paciente, ele será movido para a lixeira e poderá ser restaurado depois."
    );
    await expect(dialog.locator("strong")).toHaveText("ele será movido para a lixeira");
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(row).toBeVisible();
    expect((await api.getPatient(patient.id)).status()).toBe(200);

    await row.getByRole("button", { name: `Excluir ${patient.name}` }).click();
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: "Confirmar" }).click();

    await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
    await expect(dialog).toBeHidden();
    await expect(row).toHaveCount(0);
    await expect(emptyState(page)).toBeVisible();
    await expectNotFound(page, patient);
  });

  test("pelos detalhes", async ({ page }) => {
    await page.goto(`/pacientes/${patient.id}`);
    await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
    const dialog = deleteDialog(page);

    await page.getByRole("button", { name: "Excluir paciente" }).click();
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}$`));

    await page.getByRole("button", { name: "Excluir paciente" }).click();
    await dialog.getByRole("button", { name: "Confirmar" }).click();

    await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/$/);
    await expectNotFound(page, patient);
  });

  test("pela edição", async ({ page }) => {
    await page.goto(`/pacientes/${patient.id}/editar`);
    await expect(page.getByRole("heading", { level: 1, name: "Editar paciente" })).toBeVisible();
    const dialog = deleteDialog(page);

    await page.getByRole("button", { name: "Excluir paciente" }).click();
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: "Cancelar" }).click();
    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/editar$`));

    await page.getByRole("button", { name: "Excluir paciente" }).click();
    await dialog.getByRole("button", { name: "Confirmar" }).click();

    await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/$/);
    await expectNotFound(page, patient);
  });
});
