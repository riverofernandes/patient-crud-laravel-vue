import type { Page } from "@playwright/test";
import { expect, gotoListing, patientRows, test } from "../support/fixtures";
import { uniqueTag } from "../support/data";
import type { Patient } from "../support/api";

type AriaSort = "ascending" | "descending" | "none";

/** Names in the order the table shows them (retries through the loading state). */
async function expectOrder(page: Page, patients: Patient[]): Promise<void> {
  await expect(patientRows(page).locator("td:first-child")).toHaveText(patients.map((p) => p.name));
}

async function expectAriaSort(page: Page, name: AriaSort, birthDate: AriaSort): Promise<void> {
  await expect(page.getByRole("columnheader", { name: "Nome do paciente" })).toHaveAttribute("aria-sort", name);
  await expect(page.getByRole("columnheader", { name: "Data de nascimento" })).toHaveAttribute(
    "aria-sort",
    birthDate
  );
}

function sortParams(page: Page): { sort: string | null; direction: string | null } {
  const params = new URL(page.url()).searchParams;
  return { sort: params.get("sort"), direction: params.get("direction") };
}

test.describe("Ordenação da listagem", () => {
  test("nome e data de nascimento, aria-sort, URL, botão voltar e link direto", async ({ page, api }) => {
    const prefix = uniqueTag();
    // Name order (aa, ab, ac) differs from birth-date order (ab, ac, aa).
    const aa = await api.createPatient({ name: `${prefix} aa`, birth_date: "2001-05-10" });
    const ab = await api.createPatient({ name: `${prefix} ab`, birth_date: "1985-03-01" });
    const ac = await api.createPatient({ name: `${prefix} ac`, birth_date: "1995-07-20" });

    // Default: name ascending, no sort params in the URL.
    await gotoListing(page, prefix);
    await expectOrder(page, [aa, ab, ac]);
    await expectAriaSort(page, "ascending", "none");
    expect(sortParams(page)).toEqual({ sort: null, direction: null });

    // Clicking the active column flips it.
    await page.getByRole("button", { name: "Nome do paciente" }).click();
    await expect.poll(() => sortParams(page)).toEqual({ sort: "name", direction: "desc" });
    await expectOrder(page, [ac, ab, aa]);
    await expectAriaSort(page, "descending", "none");

    // Another column starts ascending.
    await page.getByRole("button", { name: "Data de nascimento" }).click();
    await expect.poll(() => sortParams(page)).toEqual({ sort: "birth_date", direction: "asc" });
    await expectOrder(page, [ab, ac, aa]);
    await expectAriaSort(page, "none", "ascending");

    await page.getByRole("button", { name: "Data de nascimento" }).click();
    await expect.poll(() => sortParams(page)).toEqual({ sort: "birth_date", direction: "desc" });
    await expectOrder(page, [aa, ac, ab]);
    await expectAriaSort(page, "none", "descending");
    // The search survives sorting.
    expect(new URL(page.url()).searchParams.get("search")).toBe(prefix);

    // Back walks through the sort states.
    await page.goBack();
    await expect.poll(() => sortParams(page)).toEqual({ sort: "birth_date", direction: "asc" });
    await expectOrder(page, [ab, ac, aa]);
    await expectAriaSort(page, "none", "ascending");

    await page.goBack();
    await expect.poll(() => sortParams(page)).toEqual({ sort: "name", direction: "desc" });
    await expectOrder(page, [ac, ab, aa]);
    await expectAriaSort(page, "descending", "none");

    await page.goBack();
    await expect.poll(() => sortParams(page)).toEqual({ sort: null, direction: null });
    await expectOrder(page, [aa, ab, ac]);
    await expectAriaSort(page, "ascending", "none");

    // A direct link with sort/direction opens already sorted.
    await page.goto(`/?search=${encodeURIComponent(prefix)}&sort=birth_date&direction=desc`);
    await expectOrder(page, [aa, ac, ab]);
    await expectAriaSort(page, "none", "descending");
  });
});
