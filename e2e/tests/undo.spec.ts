import { deleteDialog, emptyState, expect, gotoListing, rowFor, test, toast } from "../support/fixtures";

test.describe("Desfazer exclusão", () => {
  test("pela listagem: Desfazer no toast restaura e o paciente volta à lista", async ({ page, api }) => {
    const patient = await api.createPatient();

    await gotoListing(page, patient.name);
    await rowFor(page, patient.name).getByRole("button", { name: `Excluir ${patient.name}` }).click();
    await deleteDialog(page).getByRole("button", { name: "Confirmar" }).click();

    await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
    await expect(emptyState(page)).toBeVisible();
    expect((await api.getPatient(patient.id)).status()).toBe(404);

    const undo = page.getByRole("button", { name: `Desfazer exclusão de ${patient.name}` });
    await expect(undo).toHaveText("Desfazer");
    await undo.click();

    await expect(toast(page, "Paciente restaurado com sucesso!")).toBeVisible();
    await expect(undo).toHaveCount(0);
    await expect(rowFor(page, patient.name)).toBeVisible();
    expect((await api.getPatient(patient.id)).status()).toBe(200);
    expect(await api.listTrashed(patient.name)).toEqual([]);
  });

  test("pelos detalhes: Desfazer depois do redirecionamento restaura", async ({ page, api }) => {
    const patient = await api.createPatient();

    await page.goto(`/pacientes/${patient.id}`);
    await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
    await page.getByRole("button", { name: "Excluir paciente" }).click();
    await deleteDialog(page).getByRole("button", { name: "Confirmar" }).click();
    await expect(page).toHaveURL(/\/$/);

    // The toast survives the route change.
    await page.getByRole("button", { name: `Desfazer exclusão de ${patient.name}` }).click();
    await expect(toast(page, "Paciente restaurado com sucesso!")).toBeVisible();
    expect((await api.getPatient(patient.id)).status()).toBe(200);

    await gotoListing(page, patient.name);
    await expect(rowFor(page, patient.name)).toBeVisible();
  });
});
