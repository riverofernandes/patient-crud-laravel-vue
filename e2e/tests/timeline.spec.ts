import type { Locator, Page } from "@playwright/test";
import {
  addDaysIso,
  dayHeading,
  expect,
  randomFutureMonday,
  statusBadge,
  test,
  urlParam,
} from "../support/fixtures";
import { professionalName } from "../support/data";

const M = "−";

function timeline(page: Page): Locator {
  return page.getByRole("list", { name: "Linha do tempo do paciente" });
}

/** Day groups (top-level items of the timeline). */
function days(page: Page): Locator {
  return timeline(page).locator(":scope > li");
}

/** Every entry, top to bottom. */
function entries(page: Page): Locator {
  return timeline(page).locator(".timeline-item");
}

async function gotoTimeline(page: Page, patientId: number): Promise<void> {
  await page.goto(`/pacientes/${patientId}?aba=linha-do-tempo`);
  await expect(page.getByRole("tab", { name: "Linha do tempo" })).toHaveAttribute("aria-selected", "true");
  await expect(page.getByText("Carregando linha do tempo...")).toHaveCount(0);
  await expect(timeline(page)).toBeVisible();
}

/**
 * Heading of a São Paulo day as the timeline shows it ("Hoje, …" for today). The page decides "today"
 * when it loads, so the prefix is optional (the suite may cross midnight in between).
 */
function expectedHeading(iso: string): RegExp {
  return new RegExp(`^\\s*(Hoje, )?${dayHeading(iso)}\\s*$`);
}

test.describe("Linha do tempo do paciente", () => {
  test("atendimentos e histórico juntos, do mais recente ao mais antigo, por dia, com resumo da refração", async ({
    page,
    api,
  }) => {
    const professional = professionalName("Dra.");
    const patient = await api.createPatient();
    const edited = await api.updatePatient(patient, { name: `${patient.name} Silva` });
    const future = addDaysIso(randomFutureMonday(), 1);
    const past = "2025-03-04"; // retroactive record

    const upcoming = await api.createConsultation(patient.id, {
      scheduled_at: `${future}T09:15:00-03:00`,
      professional_name: professional,
      professional_crm: "CRM-SP 12345",
    });
    await api.setStatus(upcoming.id, "completed");
    await api.saveEyeExam(upcoming.id, {
      od: { spherical: -1.25, cylindrical: -0.5, axis: 180 },
      oe: { spherical: -1, addition: 2 },
    });
    const older = await api.createConsultation(patient.id, {
      scheduled_at: `${past}T16:00:00-03:00`,
      duration_minutes: 45,
      professional_name: professional,
    });

    // Reference: the API's own order and dates
    const body = await (await api.timeline(patient.id)).json();
    expect(body.data.map((i: { type: string }) => i.type)).toEqual(["consultation", "audit", "audit", "consultation"]);
    const auditDay = (body.data[1].occurred_at as string).slice(0, 10);

    // Starts on another tab, then the tab is synced to ?aba
    await page.goto(`/pacientes/${patient.id}`);
    await page.getByRole("tab", { name: "Linha do tempo" }).click();
    await expect.poll(() => urlParam(page, "aba")).toBe("linha-do-tempo");
    await expect(timeline(page)).toBeVisible();

    await expect(days(page)).toHaveCount(3);
    await expect(days(page).getByRole("heading", { level: 2 })).toHaveText([
      dayHeading(future),
      expectedHeading(auditDay),
      dayHeading(past),
    ]);
    await expect(entries(page)).toHaveCount(4);

    const [c1, a1, a2, c2] = [0, 1, 2, 3].map((i) => entries(page).nth(i));
    await expect(c1.getByRole("link", { name: "Atendimento" })).toBeVisible();
    await expect(c1.locator("time")).toHaveText("09:15–09:45");
    await expect(statusBadge(c1, "Realizado")).toBeVisible();
    await expect(c1).toContainText(`${professional} · CRM CRM-SP 12345`);
    await expect(c1.locator(".timeline-item__refraction")).toHaveText(
      `Refração: OD ${M}1.25 ${M}0.50 × 180° · OE ${M}1.00 Ad +2.00`
    );

    await expect(a1.locator("strong")).toHaveText("Edição");
    await expect(a1).toContainText(`Nome completo: de ${patient.name} para ${edited.name}`);
    await expect(a1.locator("time")).toHaveText(/^\d{2}:\d{2}$/);
    await expect(a2.locator("strong")).toHaveText("Cadastro");
    await expect(a2).toContainText("Paciente cadastrado.");

    await expect(c2.locator("time")).toHaveText("16:00–16:45");
    await expect(statusBadge(c2, "Agendado")).toBeVisible();
    await expect(c2.locator(".timeline-item__refraction")).toHaveCount(0);
    await expect(page.getByText("Mostrando 4 de 4 registros", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Carregar mais" })).toHaveCount(0);

    // Entries link to the consultation
    await c2.getByRole("link", { name: "Atendimento" }).click();
    await expect(page).toHaveURL(new RegExp(`/atendimentos/${older.id}$`));
  });

  test("link direto ?aba=linha-do-tempo e Carregar mais (15 por página)", async ({ page, api }) => {
    const professional = professionalName();
    const patient = await api.createPatient();
    const monday = randomFutureMonday();
    // 15 consultations (one per hour, same day) + the "Cadastro" audit = 16 entries
    for (let i = 0; i < 15; i++) {
      await api.createConsultation(patient.id, {
        scheduled_at: `${monday}T${String(7 + i).padStart(2, "0")}:00:00-03:00`,
        professional_name: professional,
      });
    }

    await gotoTimeline(page, patient.id);
    await expect(entries(page)).toHaveCount(15);
    await expect(page.getByText("Mostrando 15 de 16 registros", { exact: true })).toBeVisible();
    await expect(days(page)).toHaveCount(1);
    await expect(days(page).first().getByRole("heading", { level: 2 })).toHaveText(dayHeading(monday));
    await expect(entries(page).first().locator("time")).toHaveText("21:00–21:30"); // newest first

    await page.getByRole("button", { name: "Carregar mais" }).click();
    await expect(entries(page)).toHaveCount(16);
    await expect(page.getByText("Mostrando 16 de 16 registros", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Carregar mais" })).toHaveCount(0);
    await expect(days(page)).toHaveCount(2);
    const last = entries(page).last();
    await expect(last.locator("strong")).toHaveText("Cadastro");
    await expect(last).toBeFocused(); // focus moves to the first new entry
  });
});
