import { test as base, expect, type Locator, type Page } from "@playwright/test";
import { PatientApi } from "./api";

type Fixtures = {
  /** API client; every patient it creates (or tracks) is deleted after the test. */
  api: PatientApi;
};

export const test = base.extend<Fixtures>({
  page: async ({ page }, use) => {
    // Detach the page from the Vite dev server's HMR socket: the socket is answered by a
    // silent mock, so edits saved while the suite runs can't hot-reload a page mid-test.
    // (No effect against a production build, which has no HMR client.)
    await page.routeWebSocket(/.*/, () => undefined); // the app itself opens no sockets
    await use(page);
  },
  api: async ({}, use) => {
    const api = await PatientApi.create();
    await use(api);
    await api.dispose();
  },
});

export { expect };

/** Toast notification (success toasts are role="status", error toasts role="alert"). */
export function toast(page: Page, message: string): Locator {
  return page.locator(".b-toaster").getByText(message, { exact: true });
}

export function searchBox(page: Page): Locator {
  return page.getByRole("searchbox", { name: "Buscar por nome do paciente ou CPF" });
}

/** Data rows of the patients table (excludes the empty/busy placeholder rows). */
export function patientRows(page: Page): Locator {
  return page.locator("tbody tr").filter({ has: page.getByRole("button", { name: /^Excluir / }) });
}

export function rowFor(page: Page, name: string): Locator {
  return page.getByRole("row").filter({ has: page.getByRole("cell", { name, exact: true }) });
}

export const emptyState = (page: Page): Locator =>
  page.getByText("Nenhum paciente encontrado", { exact: true });

/** Opens the listing filtered by `term` through the URL and waits for the table to settle. */
export async function gotoListing(page: Page, term?: string): Promise<void> {
  await page.goto(term ? `/?search=${encodeURIComponent(term)}` : "/");
  await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
  await waitForTable(page);
}

export async function waitForTable(page: Page): Promise<void> {
  await expect(page.getByText("Carregando pacientes...")).toHaveCount(0);
}

/** Value shown next to a label on the detail page (<dt>label</dt><dd>value</dd>). */
export function detailValue(page: Page, label: string): Locator {
  return page
    .getByRole("term")
    .filter({ hasText: new RegExp(`^\\s*${label}\\s*$`) })
    .locator("xpath=following-sibling::dd[1]");
}

export function deleteDialog(page: Page): Locator {
  return page.getByRole("dialog", { name: "Excluir paciente" });
}

/** Form field by its visible label (exact, so "CPF" does not match other inputs). */
export function field(page: Page, label: string): Locator {
  return page.getByRole("textbox", { name: label, exact: true });
}

/* ---------- Trash ("Lixeira") ---------- */

export const trashEmptyState = (page: Page): Locator =>
  page.getByText("Nenhum paciente excluído", { exact: true });

/** Data rows of the trash table (excludes the empty/busy placeholder rows). */
export function trashRows(page: Page): Locator {
  return page.locator("tbody tr").filter({ has: page.getByRole("button", { name: /^Restaurar / }) });
}

/** Opens the trash filtered by `term` through the URL and waits for the table to settle. */
export async function gotoTrash(page: Page, term?: string): Promise<void> {
  await page.goto(term ? `/pacientes/lixeira?search=${encodeURIComponent(term)}` : "/pacientes/lixeira");
  await expect(page.getByRole("heading", { level: 1, name: "Pacientes excluídos", exact: true })).toBeVisible();
  await waitForTrashTable(page);
}

export async function waitForTrashTable(page: Page): Promise<void> {
  await expect(page.getByText("Carregando pacientes excluídos...")).toHaveCount(0);
}

/** "2026-10-26T15:51:56-03:00" -> "26/10/2026 15:51" in America/Sao_Paulo (the suite's timezone). */
export function formatDateTimeSaoPaulo(iso: string): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      timeZone: "America/Sao_Paulo",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
    })
      .formatToParts(new Date(iso))
      .map((p) => [p.type, p.value])
  );
  return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}`;
}

/** Today in America/Sao_Paulo as YYYY-MM-DD. */
export function todaySaoPaulo(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "America/Sao_Paulo" }).format(new Date());
}

/**
 * Today (America/Sao_Paulo) shifted by `days`, minus `years`, as YYYY-MM-DD. A Feb 29 that does not
 * exist in the target year becomes Feb 28 (like the API's subYearsNoOverflow). Use `ageOn` for the
 * expected age instead of assuming it, so the rare leap-day cases stay consistent.
 */
export function birthDateYearsAgo(years: number, days = 0): string {
  const [y, m, d] = todaySaoPaulo().split("-").map(Number);
  const shifted = new Date(Date.UTC(y, m - 1, d + days));
  const year = shifted.getUTCFullYear() - years;
  const month = shifted.getUTCMonth() + 1;
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
  const day = Math.min(shifted.getUTCDate(), lastDay);
  return `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

/** Age in whole years today (America/Sao_Paulo), same rule as the API. */
export function ageOn(birthDate: string, today: string = todaySaoPaulo()): number {
  const [by, bmd] = [Number(birthDate.slice(0, 4)), birthDate.slice(5)];
  const [ty, tmd] = [Number(today.slice(0, 4)), today.slice(5)];
  return ty - by - (tmd < bmd ? 1 : 0);
}

/* ---------- Listing filters ---------- */

/** The "Filtros" toggle. Its accessible name grows to "Filtros (N ativo/ativos)" with active filters. */
export function filtersToggle(page: Page): Locator {
  return page.getByRole("button", { name: /^Filtros(\s*\(\d+ ativos?\))?$/ });
}

export function filtersPanel(page: Page): Locator {
  return page.getByRole("group", { name: "Filtros" });
}

export function sexFilter(page: Page): Locator {
  return filtersPanel(page).getByRole("combobox", { name: "Sexo" });
}

/** Number inputs (role spinbutton): "Idade mínima" / "Idade máxima". */
export function ageFilter(page: Page, label: "Idade mínima" | "Idade máxima"): Locator {
  return filtersPanel(page).getByRole("spinbutton", { name: label, exact: true });
}

/** Opens the filters panel if it is closed. */
export async function openFilters(page: Page): Promise<void> {
  const toggle = filtersToggle(page);
  if ((await toggle.getAttribute("aria-expanded")) !== "true") await toggle.click();
  await expect(toggle).toHaveAttribute("aria-expanded", "true");
  await expect(filtersPanel(page)).toBeVisible();
}

/** Opens the listing with an arbitrary query string and waits for the table to settle. */
export async function gotoListingQuery(page: Page, query: Record<string, string | number>): Promise<void> {
  const qs = new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)])).toString();
  await page.goto(qs ? `/?${qs}` : "/");
  await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
  await waitForTable(page);
}

/** Query param of the current page URL. */
export function urlParam(page: Page, name: string): string | null {
  return new URL(page.url()).searchParams.get(name);
}

/* ---------- Detail: history tab ---------- */

export function historyList(page: Page): Locator {
  return page.getByRole("list", { name: "Histórico de alterações" });
}

/** Top-level entries of the history (not the nested changed-field rows). */
export function historyEntries(page: Page): Locator {
  return historyList(page).locator(":scope > li");
}

/** Changed-field row of an "Edição" entry, by the field label ("Nome completo", "Celular"...). */
export function historyChange(entry: Locator, label: string): Locator {
  return entry.locator(".history-change").filter({
    has: entry.page().locator(".history-change__field", { hasText: new RegExp(`^${label}$`) }),
  });
}

export async function waitForHistory(page: Page): Promise<void> {
  await expect(page.getByText("Carregando histórico...")).toHaveCount(0);
}

/* ---------- Consultations / agenda (Sprint 4) ---------- */

/** YYYY-MM-DD plus `days` (pure calendar arithmetic, no timezone involved). */
export function addDaysIso(iso: string, days: number): string {
  const [y, m, d] = iso.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
}

/** Monday of the week containing `iso`. */
export function mondayOf(iso: string): string {
  const [y, m, d] = iso.split("-").map(Number);
  const weekday = (new Date(Date.UTC(y, m - 1, d)).getUTCDay() + 6) % 7; // 0 = Monday
  return addDaysIso(iso, -weekday);
}

/**
 * Monday of a random week 1–4 years ahead. Tests that look at a whole week (agenda, timeline) use
 * their own far-future week, so "today" (the suite may cross midnight) and other data never matter.
 */
export function randomFutureMonday(): string {
  return mondayOf(addDaysIso(todaySaoPaulo(), 365 + Math.floor(Math.random() * 3 * 365)));
}

/** "2026-11-03T14:30:00-03:00" -> "03/11/2026" (the API always answers in São Paulo time). */
export function brDateOf(isoDateTime: string): string {
  return isoToBrDate(isoDateTime.slice(0, 10));
}

/** "2026-11-03" -> "03/11/2026". */
export function isoToBrDate(iso: string): string {
  const [y, m, d] = iso.slice(0, 10).split("-");
  return `${d}/${m}/${y}`;
}

const WEEKDAYS = ["Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado", "Domingo"];

/** "2026-11-03" -> "Terça-feira, 03/11/2026" (agenda / timeline day headings). */
export function dayHeading(iso: string): string {
  const [y, m, d] = iso.split("-").map(Number);
  const weekday = (new Date(Date.UTC(y, m - 1, d)).getUTCDay() + 6) % 7;
  return `${WEEKDAYS[weekday]}, ${isoToBrDate(iso)}`;
}

/** Status badge: the visible label is preceded by a visually hidden "Status:" prefix. */
export function statusBadge(scope: Page | Locator, label: string): Locator {
  return scope.locator(".status-badge").filter({ hasText: new RegExp(`^\\s*Status:\\s*${label}\\s*$`) });
}

/** Opens a consultation's detail page and waits for it. */
export async function gotoConsultation(page: Page, id: number): Promise<void> {
  await page.goto(`/atendimentos/${id}`);
  await expect(page.getByRole("heading", { level: 1, name: /^Atendimento \d{2}\/\d{2}\/\d{4}$/ })).toBeVisible();
}

/** Opens the agenda on a week (any day; the page normalizes to Monday) with optional filters. */
export async function gotoAgenda(
  page: Page,
  query: { semana?: string; status?: string; profissional?: string } = {}
): Promise<void> {
  const qs = new URLSearchParams(Object.entries(query).filter(([, v]) => v) as [string, string][]).toString();
  await page.goto(qs ? `/agenda?${qs}` : "/agenda");
  await expect(page.getByRole("heading", { level: 1, name: "Agenda", exact: true })).toBeVisible();
  await waitForAgenda(page);
}

export async function waitForAgenda(page: Page): Promise<void> {
  await expect(page.getByText("Carregando agenda...")).toHaveCount(0);
  await expect(page.locator(".agenda-days")).not.toHaveAttribute("aria-busy", "true");
}

/** Day section of the agenda (labelled by its heading). */
export function agendaDay(page: Page, iso: string): Locator {
  return page.locator(`section[aria-labelledby="agenda-day-${iso}"]`);
}

/** Agenda entries (one per consultation). */
export function agendaItems(scope: Page | Locator): Locator {
  return scope.locator("li.agenda-item");
}

/** The confirmation modal of "Cancelar atendimento" / "Registrar falta". */
export function statusDialog(page: Page, title: "Cancelar atendimento" | "Registrar falta"): Locator {
  return page.getByRole("dialog", { name: title });
}

/** Waits for Bootstrap's fade-in so the element (or its modal) is fully opaque. */
export async function waitForFadeIn(locator: Locator): Promise<void> {
  await expect(locator).toBeVisible();
  await expect
    .poll(() => locator.evaluate((el) => getComputedStyle(el.closest(".modal") ?? el).opacity))
    .toBe("1");
}
