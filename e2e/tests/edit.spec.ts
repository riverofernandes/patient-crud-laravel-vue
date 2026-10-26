import { expect, field, gotoListing, rowFor, test, toast } from "../support/fixtures";
import { maskCpf, maskPhone, uniqueTag } from "../support/data";

test.describe("Edição de paciente", () => {
  test("formulário vem preenchido com os dados formatados", async ({ page, api }) => {
    const patient = await api.createPatient({ birth_date: "1990-01-01", sex: "F", email: "e2e@example.com" });

    await gotoListing(page, patient.name);
    await rowFor(page, patient.name).getByRole("link", { name: `Editar ${patient.name}` }).click();

    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/editar$`));
    await expect(page.getByRole("heading", { level: 1, name: "Editar paciente" })).toBeVisible();
    await expect(field(page, "Nome completo")).toHaveValue(patient.name);
    await expect(field(page, "CPF")).toHaveValue(maskCpf(patient.cpf));
    await expect(field(page, "Data de nascimento")).toHaveValue("01/01/1990");
    await expect(page.getByRole("combobox", { name: "Sexo biológico" })).toHaveValue("F");
    await expect(field(page, "Celular")).toHaveValue(maskPhone(patient.phone));
    await expect(field(page, "E-mail (Opcional)")).toHaveValue("e2e@example.com");
  });

  test("CPF duplicado mostra o erro do servidor no campo", async ({ page, api }) => {
    const other = await api.createPatient();
    const patient = await api.createPatient();

    await page.goto(`/pacientes/${patient.id}/editar`);
    const cpf = field(page, "CPF");
    await expect(cpf).toHaveValue(maskCpf(patient.cpf));
    await cpf.fill(maskCpf(other.cpf));
    await page.getByRole("button", { name: "Atualizar paciente" }).click();

    await expect(cpf).toHaveAccessibleDescription("Já existe um paciente cadastrado com este CPF.");
    await expect(toast(page, "Verifique os campos destacados para salvar!")).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/editar$`));
    // Nothing was changed on the server
    expect((await (await api.getPatient(patient.id)).json()).data.cpf).toBe(patient.cpf);
  });

  test("edita com sucesso e mostra o toast", async ({ page, api }) => {
    const patient = await api.createPatient();
    const newName = `${uniqueTag()} Editado`;

    await page.goto(`/pacientes/${patient.id}/editar`);
    await expect(field(page, "Nome completo")).toHaveValue(patient.name);
    await field(page, "Nome completo").fill(newName);
    await page.getByRole("button", { name: "Atualizar paciente" }).click();

    await expect(toast(page, "Paciente editado com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/$/);

    await gotoListing(page, newName);
    await expect(rowFor(page, newName)).toBeVisible();
    await expect(rowFor(page, newName).getByRole("cell", { name: maskCpf(patient.cpf), exact: true })).toBeVisible();
  });
});
