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
  await expect(page.getByRole("heading", { level: 1, name: "Pacientes" })).toBeVisible();
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
