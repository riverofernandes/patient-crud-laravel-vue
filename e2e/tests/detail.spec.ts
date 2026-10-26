import type { Page } from "@playwright/test";
import { detailValue, expect, gotoListing, rowFor, test } from "../support/fixtures";
import type { Patient } from "../support/api";
import { maskCpf, maskPhone } from "../support/data";

async function expectDetail(page: Page, patient: Patient): Promise<void> {
  await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}$`));
  await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
  await expect(detailValue(page, "Nome completo")).toHaveText(patient.name);
  await expect(detailValue(page, "CPF")).toHaveText(maskCpf(patient.cpf));
  await expect(detailValue(page, "Celular")).toHaveText(maskPhone(patient.phone));
  // Stored as 1990-01-01: a timezone off-by-one would render 31/12/1989.
  await expect(detailValue(page, "Data de nascimento")).toHaveText("01/01/1990");
  await expect(detailValue(page, "Sexo biológico")).toHaveText("Feminino");
  await expect(detailValue(page, "E-mail")).toHaveText("e2e@example.com");
  await expect(detailValue(page, "Descrição")).toHaveText("Paciente criado pelo E2E");
}

test.describe("Detalhes do paciente", () => {
  let patient: Patient;

  test.beforeEach(async ({ api }) => {
    patient = await api.createPatient({
      birth_date: "1990-01-01",
      sex: "F",
      email: "e2e@example.com",
      description: "Paciente criado pelo E2E",
    });
  });

  test("abre pelo botão de olho", async ({ page }) => {
    await gotoListing(page, patient.name);
    await rowFor(page, patient.name).getByRole("link", { name: `Ver detalhes de ${patient.name}` }).click();
    await expectDetail(page, patient);
  });

  test("abre clicando na linha", async ({ page }) => {
    await gotoListing(page, patient.name);
    await rowFor(page, patient.name).getByRole("cell", { name: patient.name, exact: true }).click();
    await expectDetail(page, patient);
  });

  test("breadcrumb volta para a listagem", async ({ page }) => {
    await page.goto(`/pacientes/${patient.id}`);
    await expectDetail(page, patient);
    await page
      .getByRole("navigation", { name: "Navegação estrutural" })
      .getByRole("link", { name: "Pacientes" })
      .click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
  });
});
