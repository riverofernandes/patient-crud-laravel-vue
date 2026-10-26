import { expect, field, gotoListing, patientRows, rowFor, searchBox, test, toast, waitForTable } from "../support/fixtures";
import { generateCpf, maskCpf, uniqueTag } from "../support/data";

test.describe("Cadastro de paciente", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/pacientes/novo");
    await expect(page.getByRole("heading", { level: 1, name: "Cadastro de novo paciente" })).toBeVisible();
  });

  test("envio vazio mostra campos obrigatórios e toast", async ({ page }) => {
    await page.getByRole("button", { name: "Salvar paciente" }).click();

    await expect(toast(page, "Preencha os campos obrigatórios para salvar!")).toBeVisible();
    // name, CPF, RG, birth date, sex and phone are required; e-mail and description are optional.
    await expect(page.getByText("Campo obrigatório", { exact: true })).toHaveCount(6);
    for (const label of ["Nome completo", "CPF", "RG", "Data de nascimento", "Celular"]) {
      await expect(field(page, label)).toHaveAccessibleDescription("Campo obrigatório");
    }
    await expect(page.getByRole("combobox", { name: "Sexo biológico" })).toHaveAccessibleDescription(
      "Campo obrigatório"
    );
    await expect(field(page, "E-mail (Opcional)")).not.toHaveAccessibleDescription("Campo obrigatório");
    await expect(page).toHaveURL(/\/pacientes\/novo$/);
  });

  test("CPF inválido é rejeitado no formulário", async ({ page }) => {
    await field(page, "CPF").fill("111.111.111-11");
    await page.getByRole("button", { name: "Salvar paciente" }).click();

    await expect(field(page, "CPF")).toHaveAccessibleDescription("CPF inválido");
    await expect(page).toHaveURL(/\/pacientes\/novo$/);
  });

  test("cadastra com sucesso, volta à listagem e o paciente é encontrado", async ({ page, api }) => {
    const name = `${uniqueTag()} Cadastro`;
    const cpf = generateCpf();

    await field(page, "Nome completo").fill(name);
    // Typed as digits: the input applies the mask itself.
    await field(page, "CPF").pressSequentially(cpf);
    await expect(field(page, "CPF")).toHaveValue(maskCpf(cpf));
    await field(page, "RG").fill("123456789");
    await field(page, "Data de nascimento").pressSequentially("01011990");
    await expect(field(page, "Data de nascimento")).toHaveValue("01/01/1990");
    await page.getByRole("combobox", { name: "Sexo biológico" }).selectOption({ label: "Feminino" });
    await field(page, "Celular").pressSequentially("11987654321");
    await expect(field(page, "Celular")).toHaveValue("(11) 98765-4321");
    await field(page, "E-mail (Opcional)").fill("e2e@example.com");

    const created = page.waitForResponse(
      (res) => res.request().method() === "POST" && /\/patients$/.test(new URL(res.url()).pathname)
    );
    await page.getByRole("button", { name: "Salvar paciente" }).click();
    const response = await created;
    expect(response.status()).toBe(201);
    api.track((await response.json()).data.id);

    await expect(toast(page, "Paciente cadastrado com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/$/);
    await waitForTable(page);

    await searchBox(page).fill(name);
    await expect(patientRows(page)).toHaveCount(1);
    const row = rowFor(page, name);
    await expect(row.getByRole("cell", { name: maskCpf(cpf), exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: "(11) 98765-4321", exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: "01/01/1990", exact: true })).toBeVisible();

    // Findable by the masked CPF as well
    await gotoListing(page, maskCpf(cpf));
    await expect(rowFor(page, name)).toBeVisible();
  });
});
