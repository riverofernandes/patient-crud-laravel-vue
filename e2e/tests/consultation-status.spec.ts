import type { Locator, Page } from "@playwright/test";
import {
  addDaysIso,
  agendaItems,
  detailValue,
  expect,
  gotoAgenda,
  gotoConsultation,
  randomFutureMonday,
  statusBadge,
  statusDialog,
  test,
  toast,
  waitForFadeIn,
} from "../support/fixtures";
import type { ConsultationStatus, PatientApi } from "../support/api";
import { professionalName, tokenOf } from "../support/data";

const ACTIONS = ["Confirmar", "Marcar como realizado", "Registrar falta", "Cancelar atendimento"];
const FINAL_NOTICE = "Atendimento finalizado: apenas as anotações podem ser alteradas.";

/** Status buttons of the detail page, in order. */
function actionButtons(page: Page): Locator {
  return page.getByRole("group", { name: "Ações do atendimento" }).getByRole("button");
}

/** Creates a patient with one consultation (in a far-future week) already moved to `status`. */
async function arrange(api: PatientApi, status: ConsultationStatus = "scheduled") {
  const patient = await api.createPatient();
  const professional = professionalName();
  let consultation = await api.createConsultation(patient.id, {
    scheduled_at: `${addDaysIso(randomFutureMonday(), 3)}T10:00:00-03:00`,
    professional_name: professional,
    reason: "Revisão anual.",
  });
  if (status !== "scheduled") consultation = await api.setStatus(consultation.id, status);
  return { patient, professional, consultation };
}

/** Final state: no status buttons; editing locks every field but the notes, which save. */
async function expectOnlyNotesEditable(page: Page, api: PatientApi, id: number): Promise<void> {
  await expect(page.getByRole("group", { name: "Ações do atendimento" })).toHaveCount(0);
  for (const label of ACTIONS) await expect(page.getByRole("button", { name: label })).toHaveCount(0);

  await page.getByRole("button", { name: "Editar atendimento" }).click();
  await expect(page.getByRole("heading", { level: 2, name: "Editar atendimento" })).toBeVisible();
  await expect(page.getByText(FINAL_NOTICE, { exact: true })).toBeVisible();
  for (const id of ["#cf-date", "#cf-time", "#cf-duration", "#cf-professional", "#cf-crm", "#cf-reason"]) {
    await expect(page.locator(id), id).toBeDisabled();
  }
  // No date picker in a final state
  await expect(page.getByRole("button", { name: /^Abrir calendário/ })).toHaveCount(0);
  const notes = page.locator("#cf-notes");
  await expect(notes).toBeEnabled();
  await notes.fill("Paciente avisado por telefone.");
  await page.getByRole("button", { name: "Salvar alterações" }).click();
  await expect(toast(page, "Atendimento atualizado com sucesso!")).toBeVisible();
  await expect(detailValue(page, "Anotações")).toHaveText("Paciente avisado por telefone.");
  await expect(detailValue(page, "Queixa principal")).toHaveText("Revisão anual.");
  const saved = (await (await api.getConsultation(id)).json()).data;
  expect(saved.notes).toBe("Paciente avisado por telefone.");
}

test.describe("Status do atendimento", () => {
  test("cada status mostra só as ações permitidas (agenda)", async ({ page, api }) => {
    const patient = await api.createPatient();
    const professional = professionalName();
    const monday = randomFutureMonday();
    const statuses: ConsultationStatus[] = ["scheduled", "confirmed", "completed", "no_show", "cancelled"];
    for (const [i, status] of statuses.entries()) {
      const c = await api.createConsultation(patient.id, {
        scheduled_at: `${monday}T${String(8 + i).padStart(2, "0")}:00:00-03:00`,
        professional_name: professional,
      });
      if (status === "completed") await api.setStatus(c.id, "confirmed"); // via confirmed
      if (status !== "scheduled") await api.setStatus(c.id, status);
    }

    await gotoAgenda(page, { semana: monday, profissional: tokenOf(professional) });
    const items = agendaItems(page);
    await expect(items).toHaveCount(5);
    const expected: [string, string[]][] = [
      ["Agendado", ACTIONS],
      ["Confirmado", ACTIONS.slice(1)],
      ["Realizado", []],
      ["Faltou", []],
      ["Cancelado", []],
    ];
    for (const [i, [label, actions]] of expected.entries()) {
      const item = items.nth(i);
      const time = `${String(8 + i).padStart(2, "0")}:00`;
      await expect(statusBadge(item, label)).toBeVisible();
      const buttons = item.getByRole("group", { name: `Ações do atendimento de ${patient.name} às ${time}` });
      if (actions.length) {
        await expect(buttons.getByRole("button")).toHaveText(actions);
      } else {
        await expect(buttons).toHaveCount(0);
        await expect(item.getByRole("button")).toHaveCount(0);
      }
    }
  });

  test("Confirmar e depois Marcar como realizado; realizado só permite editar as anotações", async ({
    page,
    api,
  }) => {
    const { consultation } = await arrange(api);
    await gotoConsultation(page, consultation.id);

    await expect(statusBadge(page, "Agendado")).toBeVisible();
    await expect(actionButtons(page)).toHaveText(ACTIONS);
    await expect(page.getByRole("heading", { level: 2, name: "Prontuário" })).toHaveCount(0);

    await page.getByRole("button", { name: "Confirmar" }).click();
    await expect(toast(page, "Atendimento confirmado.")).toBeVisible();
    await expect(statusBadge(page, "Confirmado")).toBeVisible();
    await expect(actionButtons(page)).toHaveText(ACTIONS.slice(1));
    // The clicked button is gone: focus goes to the status badge
    await expect(page.locator("#consultation-status")).toBeFocused();

    await page.getByRole("button", { name: "Marcar como realizado" }).click();
    await expect(toast(page, "Atendimento marcado como realizado.")).toBeVisible();
    await expect(statusBadge(page, "Realizado")).toBeVisible();
    // Unlocks the prontuário
    await expect(page.getByRole("heading", { level: 2, name: "Prontuário" })).toBeVisible();
    expect((await (await api.getConsultation(consultation.id)).json()).data.status).toBe("completed");

    await expectOnlyNotesEditable(page, api, consultation.id);
  });

  test("Cancelar atendimento pelo modal: Voltar mantém; confirmar cancela", async ({ page, api }) => {
    const { consultation } = await arrange(api, "confirmed");
    await gotoConsultation(page, consultation.id);
    await expect(statusBadge(page, "Confirmado")).toBeVisible();

    const opener = page.getByRole("button", { name: "Cancelar atendimento" });
    await opener.click();
    const dialog = statusDialog(page, "Cancelar atendimento");
    await waitForFadeIn(dialog);
    await expect(dialog).toContainText("Deseja realmente cancelar este atendimento?");
    await dialog.getByRole("button", { name: "Voltar" }).click();
    await expect(dialog).toHaveCount(0);
    await expect(opener).toBeFocused(); // focus returns to the opener
    await expect(statusBadge(page, "Confirmado")).toBeVisible();
    expect((await (await api.getConsultation(consultation.id)).json()).data.status).toBe("confirmed");

    await opener.click();
    await waitForFadeIn(dialog);
    await dialog.getByRole("button", { name: "Cancelar atendimento" }).click();
    await expect(toast(page, "Atendimento cancelado.")).toBeVisible();
    await expect(dialog).toHaveCount(0);
    await expect(statusBadge(page, "Cancelado")).toBeVisible();
    await expect(page.locator("#consultation-status")).toBeFocused();
    await expect(page.getByRole("heading", { level: 2, name: "Prontuário" })).toHaveCount(0);
    expect((await (await api.getConsultation(consultation.id)).json()).data.status).toBe("cancelled");

    await expectOnlyNotesEditable(page, api, consultation.id);
  });

  test("Registrar falta pelo modal: Voltar mantém; confirmar registra a falta", async ({ page, api }) => {
    const { consultation } = await arrange(api);
    await gotoConsultation(page, consultation.id);

    await page.getByRole("button", { name: "Registrar falta" }).click();
    const dialog = statusDialog(page, "Registrar falta");
    await waitForFadeIn(dialog);
    await expect(dialog).toContainText("Confirma que o paciente não compareceu a este atendimento?");
    await dialog.getByRole("button", { name: "Voltar" }).click();
    await expect(dialog).toHaveCount(0);
    await expect(statusBadge(page, "Agendado")).toBeVisible();

    await page.getByRole("button", { name: "Registrar falta" }).click();
    await waitForFadeIn(dialog);
    await dialog.getByRole("button", { name: "Registrar falta" }).click();
    await expect(toast(page, "Falta registrada.")).toBeVisible();
    await expect(statusBadge(page, "Faltou")).toBeVisible();
    await expect(page.getByRole("group", { name: "Ações do atendimento" })).toHaveCount(0);
    expect((await (await api.getConsultation(consultation.id)).json()).data.status).toBe("no_show");
  });

  test("status alterado em outro lugar: o 422 mostra o erro e recarrega o atendimento", async ({ page, api }) => {
    const { consultation } = await arrange(api);
    await gotoConsultation(page, consultation.id);
    await expect(actionButtons(page)).toHaveText(ACTIONS);

    await api.setStatus(consultation.id, "cancelled"); // e.g. another user, in another tab
    await page.getByRole("button", { name: "Confirmar" }).click();
    await expect(toast(page, 'Não é possível alterar o status de "cancelado" para "confirmado".')).toBeVisible();
    await expect(statusBadge(page, "Cancelado")).toBeVisible();
    await expect(page.getByRole("group", { name: "Ações do atendimento" })).toHaveCount(0);
  });
});
