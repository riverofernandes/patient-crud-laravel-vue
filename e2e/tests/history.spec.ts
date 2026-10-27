import type { Locator, Page } from "@playwright/test";
import {
  expect,
  field,
  historyChange,
  historyEntries,
  historyList,
  test,
  toast,
  urlParam,
  waitForHistory,
} from "../support/fixtures";
import { generateCpf, maskCpf, uniqueTag } from "../support/data";

const EVENT_LABELS = ["Cadastro", "Edição", "Exclusão", "Restauração"] as const;

/** Opens the detail page straight on the "Histórico" tab (deep link) and waits for the list. */
async function gotoHistory(page: Page, id: number): Promise<void> {
  await page.goto(`/pacientes/${id}?aba=historico`);
  await expect(page.getByRole("tab", { name: "Histórico" })).toHaveAttribute("aria-selected", "true");
  await waitForHistory(page);
  await expect(historyList(page)).toBeVisible();
}

/** Event label of each entry, top to bottom. */
async function eventLabels(page: Page): Promise<string[]> {
  return historyEntries(page).locator(".history-item__title strong").allInnerTexts();
}

async function expectChange(entry: Locator, label: string, from: string, to: string): Promise<void> {
  const change = historyChange(entry, label);
  await expect(change).toHaveCount(1);
  await expect(change.locator("del")).toHaveText(from);
  await expect(change.locator("ins")).toHaveText(to);
  // Screen readers get "Campo: de X para Y"
  await expect(change).toContainText(`${label}: de ${from} para ${to}`);
}

test.describe("Histórico de alterações", () => {
  test("cadastro e edição pela UI: Cadastro e Edição com valores formatados (antigo → novo)", async ({ page, api }) => {
    const name = `${uniqueTag()} Historico`;
    const newName = `${name} Editado`;
    const cpf = generateCpf();

    // Create through the form
    await page.goto("/pacientes/novo");
    await field(page, "Nome completo").fill(name);
    await field(page, "CPF").pressSequentially(cpf);
    await field(page, "RG").fill("123456789");
    await field(page, "Data de nascimento").pressSequentially("01011990");
    await page.getByRole("combobox", { name: "Sexo biológico" }).selectOption({ label: "Feminino" });
    await field(page, "Celular").pressSequentially("11987654321");
    const created = page.waitForResponse(
      (res) => res.request().method() === "POST" && /\/patients$/.test(new URL(res.url()).pathname)
    );
    await page.getByRole("button", { name: "Salvar paciente" }).click();
    const response = await created;
    expect(response.status()).toBe(201);
    const id = (await response.json()).data.id as number;
    api.track(id);
    await expect(toast(page, "Paciente cadastrado com sucesso!")).toBeVisible();

    // Detail: "Dados" first; the tab is synced to ?aba
    await page.goto(`/pacientes/${id}`);
    await expect(page.getByRole("tab", { name: "Dados" })).toHaveAttribute("aria-selected", "true");
    await page.getByRole("tab", { name: "Histórico" }).click();
    await expect.poll(() => urlParam(page, "aba")).toBe("historico");
    await waitForHistory(page);
    await expect(historyEntries(page)).toHaveCount(1);
    await expect(historyEntries(page).first()).toContainText("Cadastro");
    await expect(historyEntries(page).first()).toContainText("Paciente cadastrado.");
    await expect(historyEntries(page).first().locator("time")).toHaveText(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/);
    await expect(page.getByText("Mostrando 1 de 1 registro", { exact: true })).toBeVisible();

    await page.getByRole("tab", { name: "Dados" }).click();
    await expect.poll(() => urlParam(page, "aba")).toBeNull();

    // Edit name and phone through the form
    await page.goto(`/pacientes/${id}/editar`);
    await expect(field(page, "Nome completo")).toHaveValue(name);
    await field(page, "Nome completo").fill(newName);
    await field(page, "Celular").fill("");
    await field(page, "Celular").pressSequentially("11912345678");
    await page.getByRole("button", { name: "Atualizar paciente" }).click();
    await expect(toast(page, "Paciente editado com sucesso!")).toBeVisible();

    await gotoHistory(page, id);
    expect(await eventLabels(page)).toEqual(["Edição", "Cadastro"]);
    const edit = historyEntries(page).first();
    // Only the changed fields, formatted like the rest of the UI
    await expect(edit.locator(".history-change")).toHaveCount(2);
    await expectChange(edit, "Nome completo", name, newName);
    await expectChange(edit, "Celular", "(11) 98765-4321", "(11) 91234-5678");
    await expect(edit).not.toContainText(maskCpf(cpf));
    await expect(page.getByText("Mostrando 2 de 2 registros", { exact: true })).toBeVisible();
  });

  test("exclusão e restauração aparecem do mais recente para o mais antigo", async ({ page, api }) => {
    const patient = await api.createPatient();
    await api.deletePatient(patient.id);
    expect((await api.restorePatient(patient.id)).status()).toBe(200);

    await gotoHistory(page, patient.id);
    expect(await eventLabels(page)).toEqual(["Restauração", "Exclusão", "Cadastro"]);
    const entries = historyEntries(page);
    await expect(entries.nth(0)).toContainText("Paciente restaurado da lixeira.");
    await expect(entries.nth(1)).toContainText("Paciente movido para a lixeira.");
    await expect(entries.nth(2)).toContainText("Paciente cadastrado.");
    for (const label of EVENT_LABELS.filter((l) => l !== "Edição")) {
      await expect(historyList(page).getByText(label, { exact: true })).toHaveCount(1);
    }
    await expect(page.getByRole("button", { name: "Carregar mais" })).toHaveCount(0);
  });

  test("Carregar mais: 10 por página por padrão, completa a lista e move o foco", async ({ page, api }) => {
    let patient = await api.createPatient({ description: "v0" });
    for (let i = 1; i <= 10; i++) {
      patient = await api.updatePatient(patient, { description: `v${i}` });
    }
    // API default page size (no per_page) is 10, newest first
    const res = await api.audits(patient.id);
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body.meta.per_page).toBe(10);
    expect(body.meta.total).toBe(11);
    expect(body.data).toHaveLength(10);
    expect(body.data[0].event).toBe("updated");
    expect(body.data[0].changes).toEqual({ description: { old: "v9", new: "v10" } });
    expect(body.data[0].user_id).toBeNull();

    await gotoHistory(page, patient.id);
    await expect(historyEntries(page)).toHaveCount(10);
    await expect(page.getByText("Mostrando 10 de 11 registros", { exact: true })).toBeVisible();
    await expectChange(historyEntries(page).first(), "Descrição", "v9", "v10");

    await page.getByRole("button", { name: "Carregar mais" }).click();
    await expect(historyEntries(page)).toHaveCount(11);
    await expect(page.getByText("Mostrando 11 de 11 registros", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Carregar mais" })).toHaveCount(0);
    const last = historyEntries(page).last();
    await expect(last).toContainText("Cadastro");
    await expect(last).toBeFocused();
  });

  test("paciente na lixeira: histórico pela API; a UI mostra paciente não encontrado", async ({ page, api }) => {
    const patient = await api.createPatient();
    await api.deletePatient(patient.id);

    const res = await api.audits(patient.id);
    expect(res.status()).toBe(200);
    const events = ((await res.json()).data as { event: string }[]).map((a) => a.event);
    expect(events).toEqual(["deleted", "created"]);
    expect((await api.audits(999_999_999)).status()).toBe(404);

    await page.goto(`/pacientes/${patient.id}?aba=historico`);
    await expect(page.getByRole("heading", { level: 1, name: "Paciente não encontrado" })).toBeVisible();
  });
});
