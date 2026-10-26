import {
  deleteDialog,
  expect,
  formatDateTimeSaoPaulo,
  gotoListing,
  gotoTrash,
  rowFor,
  searchBox,
  test,
  toast,
  trashEmptyState,
  trashRows,
  waitForTrashTable,
} from "../support/fixtures";
import { maskCpf, uniqueTag } from "../support/data";

const trashHeading = { level: 1, name: "Pacientes excluídos", exact: true } as const;

test.describe("Lixeira (pacientes excluídos)", () => {
  test("lista excluídos com CPF e data de exclusão formatados; busca por nome e CPF", async ({ page, api }) => {
    const prefix = uniqueTag();
    const first = await api.createPatient({ name: `${prefix} aa` });
    const second = await api.createPatient({ name: `${prefix} ab` });
    await api.deletePatient(first.id);
    await api.deletePatient(second.id);
    const trashed = await api.listTrashed(prefix);
    expect(trashed.map((p) => p.id).sort()).toEqual([first.id, second.id].sort());
    const deletedAt = trashed.find((p) => p.id === first.id)!.deleted_at!;

    await gotoTrash(page, prefix);
    const table = page.getByRole("table", { name: "Lista de pacientes excluídos" });
    for (const header of ["Nome do paciente", "CPF", "Data de exclusão"]) {
      await expect(table.getByRole("columnheader", { name: header, exact: true })).toBeVisible();
    }
    await expect(trashRows(page)).toHaveCount(2);
    const row = rowFor(page, first.name);
    await expect(row.getByRole("cell", { name: maskCpf(first.cpf), exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: formatDateTimeSaoPaulo(deletedAt), exact: true })).toBeVisible();
    await expect(row.getByRole("cell", { name: /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/ })).toHaveCount(1);
    await expect(row.getByRole("button", { name: `Restaurar ${first.name}` })).toBeVisible();

    // Search by name (URL synced)
    await searchBox(page).fill(first.name);
    await expect.poll(() => new URL(page.url()).searchParams.get("search")).toBe(first.name);
    await waitForTrashTable(page);
    await expect(trashRows(page)).toHaveCount(1);
    await expect(rowFor(page, first.name)).toBeVisible();

    // Search by masked CPF
    await searchBox(page).fill(maskCpf(second.cpf));
    await expect(rowFor(page, second.name)).toBeVisible();
    await expect(trashRows(page)).toHaveCount(1);

    // Nothing found
    await searchBox(page).fill(`${uniqueTag()} inexistente`);
    await expect(trashEmptyState(page)).toBeVisible();
    await expect(page.getByText("Tente buscar por outro nome ou CPF.")).toBeVisible();
    await expect(trashRows(page)).toHaveCount(0);
  });

  test("excluir pela listagem, restaurar na lixeira e voltar à listagem", async ({ page, api }) => {
    const patient = await api.createPatient();

    await gotoListing(page, patient.name);
    await rowFor(page, patient.name).getByRole("button", { name: `Excluir ${patient.name}` }).click();
    await deleteDialog(page).getByRole("button", { name: "Confirmar" }).click();
    await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
    await expect(rowFor(page, patient.name)).toHaveCount(0);

    await page.getByRole("link", { name: "Lixeira" }).click();
    await expect(page).toHaveURL(/\/pacientes\/lixeira$/);
    await expect(page.getByRole("heading", trashHeading)).toBeVisible();
    await searchBox(page).fill(patient.name);
    const row = rowFor(page, patient.name);
    await expect(row).toBeVisible();
    await expect(trashRows(page)).toHaveCount(1);

    await row.getByRole("button", { name: `Restaurar ${patient.name}` }).click();
    await expect(toast(page, "Paciente restaurado com sucesso!")).toBeVisible();
    await expect(row).toHaveCount(0);
    await expect(trashEmptyState(page)).toBeVisible();
    expect((await api.getPatient(patient.id)).status()).toBe(200);
    expect(await api.listTrashed(patient.name)).toEqual([]);

    await gotoListing(page, patient.name);
    await expect(rowFor(page, patient.name)).toBeVisible();
  });

  test("restaurar com CPF já usado por paciente ativo (409) mostra erro e mantém na lixeira", async ({ page, api }) => {
    const original = await api.createPatient();
    await api.deletePatient(original.id);
    // The CPF of a trashed patient can be reused.
    const replacement = await api.createPatient({ cpf: original.cpf, name: `${uniqueTag()} Novo` });
    expect(replacement.cpf).toBe(original.cpf);

    await gotoTrash(page, original.name);
    const row = rowFor(page, original.name);
    await row.getByRole("button", { name: `Restaurar ${original.name}` }).click();

    await expect(toast(page, "Já existe um paciente ativo com este CPF.")).toBeVisible();
    await expect(page.locator(".b-toaster [role='alert']")).toContainText("Já existe um paciente ativo com este CPF.");
    await expect(row).toBeVisible();
    await expect(row.getByRole("button", { name: `Restaurar ${original.name}` })).toBeEnabled();
    expect((await api.getPatient(original.id)).status()).toBe(404);
    expect((await api.listTrashed(original.name)).map((p) => p.id)).toEqual([original.id]);
  });

  test("restaurar paciente que já saiu da lixeira mostra aviso e remove a linha", async ({ page, api }) => {
    const patient = await api.createPatient();
    await api.deletePatient(patient.id);

    await gotoTrash(page, patient.name);
    const row = rowFor(page, patient.name);
    await expect(row).toBeVisible();
    // Restored elsewhere meanwhile.
    expect((await api.restorePatient(patient.id)).status()).toBe(200);

    await row.getByRole("button", { name: `Restaurar ${patient.name}` }).click();
    await expect(toast(page, "Este paciente não está mais na lixeira.")).toBeVisible();
    await expect(row).toHaveCount(0);
  });

  test("navegação: botão Lixeira, menu lateral e breadcrumb", async ({ page }) => {
    const sidebar = page.getByRole("complementary", { name: "Menu principal" });

    await gotoListing(page);
    await page.getByRole("link", { name: "Lixeira" }).click();
    await expect(page).toHaveURL(/\/pacientes\/lixeira$/);
    await expect(page.getByRole("heading", trashHeading)).toBeVisible();

    await sidebar.getByRole("link", { name: "Pacientes", exact: true }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();

    await sidebar.getByRole("link", { name: "Pacientes excluídos", exact: true }).click();
    await expect(page).toHaveURL(/\/pacientes\/lixeira$/);
    await expect(page.getByRole("heading", trashHeading)).toBeVisible();
    await expect(sidebar.getByRole("link", { name: "Pacientes excluídos", exact: true })).toHaveAttribute(
      "aria-current",
      "page"
    );

    await page
      .getByRole("navigation", { name: "Navegação estrutural" })
      .getByRole("link", { name: "Pacientes", exact: true })
      .click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
  });
});
