import { detailValue, expect, gotoListing, rowFor, test, todaySaoPaulo } from "../support/fixtures";
import { uniqueTag } from "../support/data";

/**
 * Today (America/Sao_Paulo) minus the given years/months, as YYYY-MM-DD.
 * The day is capped at 28 so the date always exists; that only moves it earlier, and every
 * case below subtracts an extra month, so the expected age never changes.
 */
function birthDateAgo(years: number, months: number): string {
  const [y, m, d] = todaySaoPaulo().split("-").map(Number);
  const totalMonths = y * 12 + (m - 1) - years * 12 - months;
  const year = Math.floor(totalMonths / 12);
  const month = (totalMonths % 12) + 1;
  return `${year}-${String(month).padStart(2, "0")}-${String(Math.min(d, 28)).padStart(2, "0")}`;
}

const CASES = [
  { label: "36 anos", age: 36, birth: () => birthDateAgo(36, 1) },
  { label: "1 ano", age: 1, birth: () => birthDateAgo(1, 1) },
  { label: "Menos de 1 ano", age: 0, birth: () => birthDateAgo(0, 6) },
];

test.describe("Idade do paciente", () => {
  test("aparece na listagem e nos detalhes (fuso America/Sao_Paulo)", async ({ page, api }) => {
    const prefix = uniqueTag();
    const patients = [];
    for (const [i, c] of CASES.entries()) {
      const patient = await api.createPatient({ name: `${prefix} ${String.fromCharCode(97 + i)}`, birth_date: c.birth() });
      expect(patient.age, `API age for ${patient.birth_date}`).toBe(c.age);
      patients.push({ patient, label: c.label });
    }

    await gotoListing(page, prefix);
    await expect(page.getByRole("columnheader", { name: "Idade", exact: true })).toBeVisible();
    for (const { patient, label } of patients) {
      await expect(rowFor(page, patient.name).getByRole("cell", { name: label, exact: true })).toBeVisible();
    }

    for (const { patient, label } of patients) {
      await page.goto(`/pacientes/${patient.id}`);
      await expect(detailValue(page, "Nome completo")).toHaveText(patient.name);
      await expect(detailValue(page, "Idade")).toHaveText(label);
    }
  });
});
