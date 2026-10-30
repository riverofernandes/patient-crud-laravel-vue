import {
  emptyState,
  expect,
  gotoListing,
  patientRows,
  rowFor,
  searchBox,
  test,
  waitForTable,
} from "../support/fixtures";
import { isoToBr, maskCpf, maskPhone, uniqueTag } from "../support/data";

test.describe("Listagem de pacientes", () => {
  test("renderiza a tabela com colunas e dados formatados", async ({ page, api }) => {
    const patient = await api.createPatient({ birth_date: "1985-03-01" });

    await gotoListing(page);
    for (const header of ["Nome do paciente", "CPF", "Celular", "Data de nascimento"]) {
      await expect(page.getByRole("columnheader", { name: header, exact: true })).toBeVisible();
    }
    await expect(patientRows(page).first()).toBeVisible();
    await expect(page.getByText(/Mostrando 1–\d+ de \d+/)).toBeVisible();

    await gotoListing(page, patient.name);
    const row = rowFor(page, patient.name);
    await expect(row.getByRole("cell", { name: maskCpf(patient.cpf), exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: maskPhone(patient.phone), exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: "01/03/1985", exact: true })).toBeVisible();
  });

  test("busca por nome e sincroniza a URL", async ({ page, api }) => {
    const patient = await api.createPatient();

    await gotoListing(page);
    await searchBox(page).fill(patient.name);

    await expect.poll(() => new URL(page.url()).searchParams.get("search")).toBe(patient.name);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await expect(rowFor(page, patient.name)).toBeVisible();
  });

  test("busca por CPF com máscara", async ({ page, api }) => {
    const patient = await api.createPatient();

    await gotoListing(page);
    await searchBox(page).fill(maskCpf(patient.cpf));

    await expect(page).toHaveURL(/[?&]search=/);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await expect(rowFor(page, patient.name)).toBeVisible();
    await expect(
      rowFor(page, patient.name).getByRole("cell", { name: isoToBr(patient.birth_date), exact: true })
    ).toBeVisible();
  });

  test("busca por CPF: completo ou qualquer trecho de 3+ dígitos (com ou sem máscara)", async ({ page, api }) => {
    const patient = await api.createPatient();

    await gotoListing(page);
    // Hint under the search box: a partial CPF needs at least 3 digits
    await expect(searchBox(page)).toHaveAccessibleDescription("CPF: informe 3 ou mais dígitos");

    await searchBox(page).fill(patient.cpf);
    await expect.poll(() => new URL(page.url()).searchParams.get("search")).toBe(patient.cpf);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await expect(rowFor(page, patient.name)).toBeVisible();

    // Partial CPF (masked prefix, unmasked middle run): other patients may share the digits, so only assert
    // that this patient is among the results.
    for (const partial of [maskCpf(patient.cpf).slice(0, 11), patient.cpf.slice(3, 9)]) {
      await searchBox(page).fill(partial);
      await expect.poll(() => new URL(page.url()).searchParams.get("search")).toBe(partial);
      await waitForTable(page);
      await expect(rowFor(page, patient.name)).toBeVisible();
    }
  });

  test("mostra estado vazio quando a busca não encontra nada", async ({ page }) => {
    await gotoListing(page);
    await searchBox(page).fill(`${uniqueTag()} inexistente`);

    await expect(emptyState(page)).toBeVisible();
    await expect(page.getByText("Tente buscar por outro nome ou CPF.")).toBeVisible();
    await expect(patientRows(page)).toHaveCount(0);
    await expect(page.getByRole("menubar", { name: "Paginação" })).toHaveCount(0);
  });

  test("paginação: próxima/anterior, ?page na URL e botão voltar", async ({ page, api }) => {
    const prefix = uniqueTag();
    const patients = await api.createMany(12, prefix);

    await gotoListing(page, prefix);
    const pagination = page.getByRole("menubar", { name: "Paginação" });
    await expect(patientRows(page)).toHaveCount(10);
    await expect(page.getByText("Mostrando 1–10 de 12", { exact: true })).toBeVisible();

    // Next
    await pagination.getByRole("menuitem", { name: "Próxima página" }).click();
    await expect(page).toHaveURL(/[?&]page=2(&|$)/);
    await expect(page.getByText("Mostrando 11–12 de 12", { exact: true })).toBeVisible();
    await expect(patientRows(page)).toHaveCount(2);
    await expect(rowFor(page, patients[11].name)).toBeVisible();
    await expect(pagination.getByRole("menuitemradio", { name: "Ir para a página 2" })).toBeChecked();

    // Prev
    await pagination.getByRole("menuitem", { name: "Página anterior" }).click();
    await expect(page).not.toHaveURL(/[?&]page=/);
    await expect(page.getByText("Mostrando 1–10 de 12", { exact: true })).toBeVisible();
    await expect(rowFor(page, patients[0].name)).toBeVisible();

    // Browser back walks through the visited pages
    await page.goBack();
    await expect(page).toHaveURL(/[?&]page=2(&|$)/);
    await expect(page.getByText("Mostrando 11–12 de 12", { exact: true })).toBeVisible();
    await page.goBack();
    await expect(page).not.toHaveURL(/[?&]page=/);
    await expect(page.getByText("Mostrando 1–10 de 12", { exact: true })).toBeVisible();

    // Direct link to page 2 keeps the search
    await page.goto(`/?search=${encodeURIComponent(prefix)}&page=2`);
    await expect(searchBox(page)).toHaveValue(prefix);
    await expect(page.getByText("Mostrando 11–12 de 12", { exact: true })).toBeVisible();
  });
});
