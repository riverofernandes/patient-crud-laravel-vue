import type { Page, Request } from "@playwright/test";
import {
  addDaysIso,
  dayHeading,
  detailValue,
  expect,
  isoToBrDate,
  randomFutureMonday,
  statusBadge,
  test,
  toast,
  todaySaoPaulo,
  urlParam,
} from "../support/fixtures";
import { professionalName, uniqueTag } from "../support/data";

const OVERLAP = "Este profissional já tem um atendimento neste horário.";

/** Consultation POSTs the page sends. */
function recordCreates(page: Page): Request[] {
  const sent: Request[] = [];
  page.on("request", (req) => {
    if (req.method() === "POST" && /\/consultations$/.test(new URL(req.url()).pathname)) sent.push(req);
  });
  return sent;
}

async function gotoNewConsultation(page: Page, patientId: number, patientName: string): Promise<void> {
  await page.goto(`/pacientes/${patientId}/atendimentos/novo`);
  await expect(page.getByRole("heading", { level: 1, name: "Novo atendimento" })).toBeVisible();
  await expect(page.getByText(`Paciente: ${patientName}`)).toBeVisible();
}

/** Fills date (DD/MM/AAAA), time and professional; submits; returns the POST response. */
async function submitConsultation(page: Page, values: { date: string; time: string; professional: string }) {
  await page.locator("#cf-date").fill(isoToBrDate(values.date));
  await page.locator("#cf-time").fill(values.time);
  await page.locator("#cf-professional").fill(values.professional);
  const response = page.waitForResponse(
    (res) => res.request().method() === "POST" && /\/consultations$/.test(new URL(res.url()).pathname)
  );
  await page.getByRole("button", { name: "Agendar atendimento" }).click();
  return response;
}

test.describe("Novo atendimento", () => {
  test("pela aba Atendimentos do paciente: agenda, mostra o toast e abre o detalhe", async ({ page, api }) => {
    const patient = await api.createPatient();
    const professional = professionalName("Dra.");
    const date = addDaysIso(randomFutureMonday(), 1); // a Tuesday

    await page.goto(`/pacientes/${patient.id}`);
    await page.getByRole("tab", { name: "Atendimentos" }).click();
    await expect.poll(() => urlParam(page, "aba")).toBe("atendimentos");
    await expect(page.getByText("Nenhum atendimento registrado", { exact: true })).toBeVisible();
    await page.getByRole("tabpanel", { name: "Atendimentos" }).getByRole("link", { name: "Novo atendimento" }).click();

    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/atendimentos/novo$`));
    await expect(page.getByRole("heading", { level: 1, name: "Novo atendimento" })).toBeVisible();
    await expect(page.getByText(`Paciente: ${patient.name}`)).toBeVisible();
    // Defaults: today (São Paulo) and 30 minutes. Compared right away (the suite may cross midnight).
    await expect(page.locator("#cf-date")).toHaveValue(isoToBrDate(todaySaoPaulo()));
    await expect(page.locator("#cf-duration")).toHaveValue("30");
    await expect(page.locator("#cf-duration option")).toHaveText([
      "15 minutos",
      "30 minutos",
      "45 minutos",
      "60 minutos",
      "90 minutos",
    ]);

    // The date field is masked: digits only become DD/MM/AAAA.
    await page.locator("#cf-date").fill("");
    await page.locator("#cf-date").pressSequentially(isoToBrDate(date).replace(/\D/g, ""));
    await expect(page.locator("#cf-date")).toHaveValue(isoToBrDate(date));
    await page.locator("#cf-time").fill("14:30");
    await page.locator("#cf-duration").selectOption("45");
    await page.locator("#cf-professional").fill(professional);
    await page.locator("#cf-crm").fill("123456/SP");
    await page.locator("#cf-reason").fill("Visão embaçada para perto.");
    await page.locator("#cf-notes").fill("Trazer os óculos atuais.");

    const created = page.waitForResponse(
      (res) => res.request().method() === "POST" && /\/consultations$/.test(new URL(res.url()).pathname)
    );
    await page.getByRole("button", { name: "Agendar atendimento" }).click();
    const response = await created;
    expect(response.status()).toBe(201);
    const body = (await response.json()).data;
    // Sent without offset (read as São Paulo); answered with -03:00
    expect(response.request().postDataJSON()).toMatchObject({
      scheduled_at: `${date}T14:30`,
      duration_minutes: 45,
      professional_name: professional,
      professional_crm: "123456/SP",
    });
    expect(body.scheduled_at).toBe(`${date}T14:30:00-03:00`);
    expect(body.status).toBe("scheduled");

    await expect(toast(page, "Atendimento agendado com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/atendimentos/${body.id}$`));
    await expect(page.getByRole("heading", { level: 1, name: `Atendimento ${isoToBrDate(date)}` })).toBeVisible();
    await expect(statusBadge(page, "Agendado")).toBeVisible();
    await expect(detailValue(page, "Paciente")).toHaveText(patient.name);
    await expect(detailValue(page, "Data")).toHaveText(dayHeading(date));
    await expect(detailValue(page, "Horário")).toHaveText(/^\s*14:30–15:15\s*\(45 min\)\s*$/);
    await expect(detailValue(page, "Profissional")).toHaveText(professional);
    await expect(detailValue(page, "CRM")).toHaveText("123456/SP");
    await expect(detailValue(page, "Queixa principal")).toHaveText("Visão embaçada para perto.");
    await expect(detailValue(page, "Anotações")).toHaveText("Trazer os óculos atuais.");

    // Back on the patient: the tab lists it
    await page.getByRole("link", { name: patient.name }).first().click();
    await expect(page.getByRole("tab", { name: "Atendimentos" })).toHaveAttribute("aria-selected", "true");
    const list = page.getByRole("list", { name: "Atendimentos do paciente" });
    await expect(list.getByRole("listitem")).toHaveCount(1);
    await expect(list.getByRole("listitem").first()).toContainText(isoToBrDate(date));
    await expect(list.getByRole("listitem").first()).toContainText("14:30–15:15");
    await expect(statusBadge(list, "Agendado")).toBeVisible();
    await expect(list.getByRole("link", { name: `Atendimento de ${isoToBrDate(date)}, ${professional}` })).toBeVisible();
  });

  test("validação no cliente: obrigatórios, data inválida e profissional curto (nada é enviado)", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    const creates = recordCreates(page);
    await gotoNewConsultation(page, patient.id, patient.name);

    const date = page.locator("#cf-date");
    await date.fill("");
    await page.getByRole("button", { name: "Agendar atendimento" }).click();

    await expect(toast(page, "Preencha os campos obrigatórios para salvar!")).toBeVisible();
    await expect(date).toHaveAccessibleDescription("Campo obrigatório");
    await expect(page.locator("#cf-time")).toHaveAccessibleDescription("Campo obrigatório");
    await expect(page.locator("#cf-professional")).toHaveAccessibleDescription("Campo obrigatório");
    await expect(page.locator("#cf-crm")).toHaveAccessibleDescription("");
    await expect(date).toBeFocused(); // first invalid field
    await expect(date).toHaveClass(/is-invalid/);

    // Typing clears the field's error
    await page.locator("#cf-professional").fill("Dr");
    await expect(page.locator("#cf-professional")).toHaveAccessibleDescription("");

    await date.pressSequentially("31022027"); // no Feb 31
    await page.locator("#cf-time").fill("09:00");
    await page.getByRole("button", { name: "Agendar atendimento" }).click();
    await expect(toast(page, "Verifique os campos destacados.")).toBeVisible();
    await expect(date).toHaveAccessibleDescription("Data inválida");
    await expect(page.locator("#cf-professional")).toHaveAccessibleDescription("Informe ao menos 3 caracteres");
    await expect(page.locator("#cf-time")).toHaveAccessibleDescription("");

    expect(creates).toHaveLength(0);
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/atendimentos/novo$`));

    // "Cancelar" goes back to the patient's Atendimentos tab
    await page.getByRole("link", { name: "Cancelar" }).click();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}\\?aba=atendimentos$`));
  });

  test("conflito de horário: mesmo profissional com outra grafia; horário seguido e horário cancelado liberam", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    const tag = uniqueTag();
    const accented = `Dr. Érico ${tag}`;
    // Same identity: case, accents and extra spaces are ignored
    const variant = `  dr.  ERICO ${tag.toLowerCase()} `;
    const day = addDaysIso(randomFutureMonday(), 2);

    await api.createConsultation(patient.id, {
      scheduled_at: `${day}T14:00:00-03:00`,
      duration_minutes: 30,
      professional_name: accented,
    });
    const cancelled = await api.createConsultation(patient.id, {
      scheduled_at: `${day}T16:00:00-03:00`,
      duration_minutes: 30,
      professional_name: accented,
    });
    await api.setStatus(cancelled.id, "cancelled");

    // 14:15 overlaps [14:00, 14:30)
    await gotoNewConsultation(page, patient.id, patient.name);
    const conflict = await submitConsultation(page, { date: day, time: "14:15", professional: variant });
    expect(conflict.status()).toBe(422);
    expect((await conflict.json()).errors.scheduled_at).toEqual([OVERLAP]);

    const time = page.locator("#cf-time");
    await expect(page.locator("#cf-time-error")).toHaveText(OVERLAP);
    await expect(time).toHaveAccessibleDescription(OVERLAP);
    await expect(time).toHaveClass(/is-invalid/);
    await expect(toast(page, OVERLAP)).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/atendimentos/novo$`));

    // Back-to-back (half-open intervals): 14:30 is free; editing the time clears the error
    await time.fill("14:30");
    await expect(page.locator("#cf-time-error")).toHaveCount(0);
    const created = page.waitForResponse(
      (res) => res.request().method() === "POST" && /\/consultations$/.test(new URL(res.url()).pathname)
    );
    await page.getByRole("button", { name: "Agendar atendimento" }).click();
    expect((await created).status()).toBe(201);
    await expect(toast(page, "Atendimento agendado com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/atendimentos\/\d+$/);

    // The cancelled 16:00 slot does not block
    await gotoNewConsultation(page, patient.id, patient.name);
    const overCancelled = await submitConsultation(page, { date: day, time: "16:00", professional: variant });
    expect(overCancelled.status()).toBe(201);
    await expect(toast(page, "Atendimento agendado com sucesso!")).toBeVisible();
    await expect(page).toHaveURL(/\/atendimentos\/\d+$/);
  });
});
