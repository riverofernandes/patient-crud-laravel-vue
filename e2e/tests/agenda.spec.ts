import type { Page } from "@playwright/test";
import {
  addDaysIso,
  agendaDay,
  agendaItems,
  dayHeading,
  expect,
  gotoAgenda,
  isoToBrDate,
  mondayOf,
  randomFutureMonday,
  statusBadge,
  statusDialog,
  test,
  toast,
  todaySaoPaulo,
  urlParam,
  waitForAgenda,
  waitForFadeIn,
} from "../support/fixtures";
import { professionalName, tokenOf, uniqueTag } from "../support/data";

/** "Semana de 26/10 a 01/11/2026". */
function weekLabel(monday: string): string {
  return `Semana de ${isoToBrDate(monday).slice(0, 5)} a ${isoToBrDate(addDaysIso(monday, 6))}`;
}

function weekText(page: Page) {
  return page.locator(".agenda-week");
}

function statusCheckbox(page: Page, label: string) {
  return page.getByRole("group", { name: "Status" }).getByRole("checkbox", { name: label, exact: true });
}

test.describe("Agenda", () => {
  test("navegação entre semanas: ?semana normalizada para a segunda, anterior/próxima, Hoje e voltar", async ({
    page,
  }) => {
    const monday = randomFutureMonday();
    const wednesday = addDaysIso(monday, 2);

    // Sidebar link
    await page.goto("/");
    await page.getByRole("link", { name: "Agenda", exact: true }).click();
    await expect(page).toHaveURL(/\/agenda$/);
    await waitForAgenda(page);
    const thisMonday = mondayOf(todaySaoPaulo());
    await expect(weekText(page)).toHaveText(weekLabel(thisMonday));
    const hoje = page.getByRole("button", { name: "Hoje", exact: true });
    await expect(hoje).toHaveAttribute("aria-current", "date");
    await expect(page.locator("section.agenda-day")).toHaveCount(7);
    await expect(page.locator("section.agenda-day.is-today")).toHaveCount(1);
    await expect(page.locator("section.agenda-day.is-today h2")).toContainText(dayHeading(todaySaoPaulo()));

    // Any day of the week -> that week's Monday (URL replaced)
    await gotoAgenda(page, { semana: wednesday });
    await expect.poll(() => urlParam(page, "semana")).toBe(monday);
    await expect(weekText(page)).toHaveText(weekLabel(monday));
    await expect(hoje).not.toHaveAttribute("aria-current", "date");
    const headings = page.locator("section.agenda-day h2");
    await expect(headings.first()).toContainText(dayHeading(monday));
    await expect(headings.last()).toContainText(dayHeading(addDaysIso(monday, 6)));

    // Next / previous
    const loaded = (from: string) =>
      page.waitForResponse(
        (res) =>
          new URL(res.url()).pathname.endsWith("/consultations") &&
          new URL(res.url()).searchParams.get("date_from") === from
      );
    let response = loaded(addDaysIso(monday, 7));
    await page.getByRole("button", { name: "Próxima semana" }).click();
    expect(new URL((await response).url()).searchParams.get("date_to")).toBe(addDaysIso(monday, 13));
    await expect.poll(() => urlParam(page, "semana")).toBe(addDaysIso(monday, 7));
    await expect(weekText(page)).toHaveText(weekLabel(addDaysIso(monday, 7)));

    response = loaded(addDaysIso(monday, -7));
    await page.getByRole("button", { name: "Semana anterior" }).click();
    await expect.poll(() => urlParam(page, "semana")).toBe(monday); // next click reads the route
    await page.getByRole("button", { name: "Semana anterior" }).click();
    await response;
    await expect.poll(() => urlParam(page, "semana")).toBe(addDaysIso(monday, -7));
    await expect(weekText(page)).toHaveText(weekLabel(addDaysIso(monday, -7)));

    // Weeks are history entries: "back" returns to the previous one
    await page.goBack();
    await expect.poll(() => urlParam(page, "semana")).toBe(monday);
    await expect(weekText(page)).toHaveText(weekLabel(monday));

    // "Hoje": current week, no ?semana
    await hoje.click();
    await expect.poll(() => urlParam(page, "semana")).toBeNull();
    await expect(weekText(page)).toHaveText(weekLabel(mondayOf(todaySaoPaulo())));
    await expect(hoje).toHaveAttribute("aria-current", "date");

    // Invalid value -> current week and the param is dropped
    await gotoAgenda(page, { semana: "2027-02-31" });
    await expect.poll(() => urlParam(page, "semana")).toBeNull();
    await expect(weekText(page)).toHaveText(weekLabel(mondayOf(todaySaoPaulo())));
  });

  test.describe("com o navegador em UTC", () => {
    // A naive new Date(...) would put 23:30 −03:00 (02:30 UTC) on the next day in this zone.
    test.use({ timezoneId: "UTC" });

    test("agrupa no dia certo de São Paulo, em ordem de horário (inclui 23:30 −03:00)", async ({ page, api }) => {
      const patient = await api.createPatient();
      const professional = professionalName();
      const monday = randomFutureMonday();
      const tuesday = addDaysIso(monday, 1);
      const thursday = addDaysIso(monday, 3);
      // Created out of order
      await api.createConsultation(patient.id, {
        scheduled_at: `${tuesday}T23:30:00-03:00`,
        professional_name: professional,
      });
      await api.createConsultation(patient.id, {
        scheduled_at: `${thursday}T13:00:00Z`, // 10:00 in São Paulo
        duration_minutes: 45,
        professional_name: professional,
      });
      await api.createConsultation(patient.id, {
        scheduled_at: `${tuesday}T08:00`, // no offset: São Paulo
        professional_name: professional,
      });

      await gotoAgenda(page, { semana: monday, profissional: tokenOf(professional) });
      await expect(agendaItems(page)).toHaveCount(3);

      const tue = agendaDay(page, tuesday);
      await expect(tue.getByRole("heading", { level: 2 })).toContainText(dayHeading(tuesday));
      await expect(tue.getByRole("heading", { level: 2 })).toContainText("2 atendimentos");
      await expect(agendaItems(tue).locator(".agenda-item__time")).toHaveText(["08:00–08:30", "23:30–00:00"]);
      await expect(agendaItems(tue).first()).toContainText(patient.name);
      await expect(agendaItems(tue).first()).toContainText(professional);
      await expect(statusBadge(agendaItems(tue).first(), "Agendado")).toBeVisible();

      const wed = agendaDay(page, addDaysIso(monday, 2));
      await expect(wed).toContainText("Nenhum atendimento");
      await expect(agendaItems(wed)).toHaveCount(0);

      const thu = agendaDay(page, thursday);
      await expect(thu.getByRole("heading", { level: 2 })).toContainText("1 atendimento");
      await expect(agendaItems(thu).locator(".agenda-item__time")).toHaveText(["10:00–10:45"]);

      for (const day of [monday, addDaysIso(monday, 4), addDaysIso(monday, 5), addDaysIso(monday, 6)]) {
        await expect(agendaItems(agendaDay(page, day))).toHaveCount(0);
      }
    });
  });

  test("filtros de status e de profissional (sem acento/maiúsculas) ficam na URL", async ({ page, api }) => {
    const patient = await api.createPatient();
    const tag = uniqueTag();
    const erico = `Dr. Érico ${tag}`;
    const ana = `Dra. Ana ${tag}`;
    const monday = randomFutureMonday();
    const at = (day: number, time: string) => `${addDaysIso(monday, day)}T${time}:00-03:00`;
    await api.createConsultation(patient.id, { scheduled_at: at(0, "09:00"), professional_name: erico });
    const confirmed = await api.createConsultation(patient.id, { scheduled_at: at(1, "09:00"), professional_name: erico });
    await api.setStatus(confirmed.id, "confirmed");
    const cancelled = await api.createConsultation(patient.id, { scheduled_at: at(2, "09:00"), professional_name: erico });
    await api.setStatus(cancelled.id, "cancelled");
    await api.createConsultation(patient.id, { scheduled_at: at(3, "15:00"), professional_name: ana });

    await gotoAgenda(page, { semana: monday });
    const professionalBox = page.getByRole("searchbox", { name: "Profissional" });

    // Professional (debounced): the shared tag matches both professionals
    await professionalBox.fill(tag.toLowerCase());
    await expect.poll(() => urlParam(page, "profissional")).toBe(tag.toLowerCase());
    await waitForAgenda(page);
    await expect(agendaItems(page)).toHaveCount(4);

    // Accents and case are ignored: "erico" finds "Érico" only
    await professionalBox.fill(`ERICO ${tag}`);
    await expect.poll(() => urlParam(page, "profissional")).toBe(`ERICO ${tag}`);
    await waitForAgenda(page);
    await expect(agendaItems(page)).toHaveCount(3);
    await expect(page.locator(".agenda-item__professional")).toHaveText([erico, erico, erico]);

    // Status (several, canonical order in the URL)
    await statusCheckbox(page, "Cancelado").check();
    await expect.poll(() => urlParam(page, "status")).toBe("cancelled");
    await waitForAgenda(page);
    await expect(agendaItems(page)).toHaveCount(1);
    await expect(statusBadge(agendaItems(page).first(), "Cancelado")).toBeVisible();

    await statusCheckbox(page, "Agendado").check();
    await expect.poll(() => urlParam(page, "status")).toBe("scheduled,cancelled");
    await waitForAgenda(page);
    await expect(agendaItems(page)).toHaveCount(2);

    await statusCheckbox(page, "Agendado").uncheck();
    await expect.poll(() => urlParam(page, "status")).toBe("cancelled"); // next toggle reads the route
    await statusCheckbox(page, "Cancelado").uncheck();
    await expect.poll(() => urlParam(page, "status")).toBeNull();
    await waitForAgenda(page);
    await expect(agendaItems(page)).toHaveCount(3);

    // Clearing the box removes the param
    await professionalBox.fill("");
    await expect.poll(() => urlParam(page, "profissional")).toBeNull();

    // Filters come back from the URL (link / reload)
    await gotoAgenda(page, { semana: monday, status: "confirmed,scheduled", profissional: tag });
    await expect(statusCheckbox(page, "Agendado")).toBeChecked();
    await expect(statusCheckbox(page, "Confirmado")).toBeChecked();
    await expect(statusCheckbox(page, "Cancelado")).not.toBeChecked();
    await expect(professionalBox).toHaveValue(tag);
    await expect(agendaItems(page)).toHaveCount(3); // Érico scheduled + confirmed, Ana scheduled
    await expect(agendaDay(page, addDaysIso(monday, 3))).toContainText(ana);
  });

  test("ação rápida com modal, link do paciente e link do atendimento", async ({ page, api }) => {
    const patient = await api.createPatient();
    const professional = professionalName();
    const monday = randomFutureMonday();
    const first = await api.createConsultation(patient.id, {
      scheduled_at: `${monday}T09:00:00-03:00`,
      professional_name: professional,
    });
    const second = await api.createConsultation(patient.id, {
      scheduled_at: `${monday}T10:30:00-03:00`,
      professional_name: professional,
    });

    await gotoAgenda(page, { semana: monday, profissional: tokenOf(professional) });
    const items = agendaItems(page);
    await expect(items).toHaveCount(2);

    // Confirm: no modal
    await page.getByRole("button", { name: `Confirmar: atendimento de ${patient.name} às 09:00` }).click();
    await expect(toast(page, "Atendimento confirmado.")).toBeVisible();
    await expect(statusBadge(items.nth(0), "Confirmado")).toBeVisible();
    await expect(page.locator(`#agenda-item-${first.id}`)).toBeFocused();

    // Cancel: modal; "Voltar" keeps it
    const cancel = page.getByRole("button", { name: `Cancelar atendimento: atendimento de ${patient.name} às 10:30` });
    await cancel.click();
    const dialog = statusDialog(page, "Cancelar atendimento");
    await waitForFadeIn(dialog);
    await dialog.getByRole("button", { name: "Voltar" }).click();
    await expect(dialog).toHaveCount(0);
    await expect(statusBadge(items.nth(1), "Agendado")).toBeVisible();

    await cancel.click();
    await waitForFadeIn(dialog);
    await dialog.getByRole("button", { name: "Cancelar atendimento" }).click();
    await expect(toast(page, "Atendimento cancelado.")).toBeVisible();
    await expect(statusBadge(items.nth(1), "Cancelado")).toBeVisible();
    await expect(items.nth(1).getByRole("button")).toHaveCount(0);
    await expect(page.locator(`#agenda-item-${second.id}`)).toBeFocused();
    expect((await (await api.getConsultation(second.id)).json()).data.status).toBe("cancelled");

    // Consultation link
    await page.getByRole("link", { name: `Ver atendimento de ${patient.name} às 09:00` }).click();
    await expect(page).toHaveURL(new RegExp(`/atendimentos/${first.id}$`));
    await expect(page.getByRole("heading", { level: 1, name: `Atendimento ${isoToBrDate(monday)}` })).toBeVisible();
    await page.goBack();
    await waitForAgenda(page);

    // Patient link
    await items.nth(0).getByRole("link", { name: patient.name, exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}$`));
    await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
  });
});
