import type { Page } from "@playwright/test";
import { expect, gotoListing, patientRows, searchBox, test, waitForTable } from "../support/fixtures";
import { uniqueTag } from "../support/data";

function param(page: Page, name: string): string | null {
  return new URL(page.url()).searchParams.get(name);
}

test.describe("Itens por página", () => {
  test("25 por página: ?per_page, páginas voltam à 1 e convive com busca e ordenação", async ({ page, api }) => {
    const prefix = uniqueTag();
    const patients = await api.createMany(11, prefix);

    await gotoListing(page, prefix);
    const perPage = page.getByRole("combobox", { name: "Itens por página" });
    const pagination = page.getByRole("menubar", { name: "Paginação" });
    await expect(perPage).toHaveValue("10");
    await expect(perPage.locator("option")).toHaveText(["10", "25", "50"]);
    await expect(patientRows(page)).toHaveCount(10);

    // Sorting from page 2 goes back to page 1 (same birth date for all: ties broken by id).
    await pagination.getByRole("menuitem", { name: "Próxima página" }).click();
    await expect(page).toHaveURL(/[?&]page=2(&|$)/);
    await page.getByRole("button", { name: "Data de nascimento" }).click();
    await expect.poll(() => param(page, "sort")).toBe("birth_date");
    expect(param(page, "page")).toBeNull();
    await expect(page.getByText("Mostrando 1–10 de 11", { exact: true })).toBeVisible();
    await expect(patientRows(page).first().locator("td").first()).toHaveText(patients[0].name);

    // Back to the default sort, go to page 2, then change the page size: back to page 1.
    await gotoListing(page, prefix);
    await pagination.getByRole("menuitem", { name: "Próxima página" }).click();
    await expect(page).toHaveURL(/[?&]page=2(&|$)/);
    await expect(page.getByText("Mostrando 11–11 de 11", { exact: true })).toBeVisible();

    await perPage.selectOption("25");
    await expect.poll(() => param(page, "per_page")).toBe("25");
    expect(param(page, "page")).toBeNull();
    expect(param(page, "search")).toBe(prefix);
    await expect(page.getByText("Mostrando 1–11 de 11", { exact: true })).toBeVisible();
    await expect(patientRows(page)).toHaveCount(11);

    // Sorting keeps the page size (and the search).
    await page.getByRole("button", { name: "Nome do paciente" }).click();
    await expect.poll(() => param(page, "direction")).toBe("desc");
    expect(param(page, "per_page")).toBe("25");
    expect(param(page, "search")).toBe(prefix);
    await expect(perPage).toHaveValue("25");
    await expect(patientRows(page).first().locator("td").first()).toHaveText(patients[10].name);
    await expect(patientRows(page)).toHaveCount(11);

    // A new search keeps the page size and the sort.
    await searchBox(page).fill(patients[0].name);
    await expect.poll(() => param(page, "search")).toBe(patients[0].name);
    await waitForTable(page);
    expect(param(page, "per_page")).toBe("25");
    expect(param(page, "sort")).toBe("name");
    expect(param(page, "direction")).toBe("desc");
    await expect(patientRows(page)).toHaveCount(1);
    await expect(perPage).toHaveValue("25");

    // Reload keeps everything (URL is the source of truth).
    await page.reload();
    await expect(perPage).toHaveValue("25");
    await expect(searchBox(page)).toHaveValue(patients[0].name);
    await expect(patientRows(page)).toHaveCount(1);
  });
});
