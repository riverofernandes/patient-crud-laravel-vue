import type { Page } from "@playwright/test";
import {
  ageFilter,
  ageOn,
  birthDateYearsAgo,
  emptyState,
  expect,
  filtersPanel,
  filtersToggle,
  gotoListing,
  gotoListingQuery,
  openFilters,
  patientRows,
  rowFor,
  sexFilter,
  test,
  urlParam,
  waitForTable,
} from "../support/fixtures";
import type { Patient } from "../support/api";
import { uniqueTag } from "../support/data";

// Every test searches by its own unique prefix: seed data and the other worker's patients also
// match any sex/age filter.

/** Names of the rows on screen, sorted (the listing sorts by name; the prefix keeps it stable). */
async function rowNames(page: Page): Promise<string[]> {
  await waitForTable(page);
  return (await patientRows(page).locator("td:first-child").allInnerTexts()).map((t) => t.trim()).sort();
}

const names = (patients: Patient[]): string[] => patients.map((p) => p.name).sort();

test.describe("Filtros da listagem", () => {
  test("sexo: filtra, sincroniza a URL, sobrevive ao reload e o botão voltar restaura", async ({ page, api }) => {
    const prefix = uniqueTag();
    const female = await api.createPatient({ name: `${prefix} aa`, sex: "F" });
    const male = await api.createPatient({ name: `${prefix} ab`, sex: "M" });

    await gotoListing(page, prefix);
    await expect(patientRows(page)).toHaveCount(2);
    // Closed by default, toggle exposes the state and the panel it controls.
    const toggle = page.getByRole("button", { name: "Filtros", exact: true });
    await expect(toggle).toHaveAttribute("aria-expanded", "false");
    await expect(toggle).toHaveAttribute("aria-controls", "patient-filters");
    await expect(filtersPanel(page)).toBeHidden();

    await openFilters(page);
    await expect(sexFilter(page)).toHaveValue("");
    await sexFilter(page).selectOption({ label: "Feminino" });
    await expect.poll(() => urlParam(page, "sex")).toBe("F");
    expect(urlParam(page, "search")).toBe(prefix);
    expect(await rowNames(page)).toEqual([female.name]);
    await expect(page.getByRole("button", { name: "Filtros (1 ativo)", exact: true })).toBeVisible();

    await sexFilter(page).selectOption({ label: "Masculino" });
    await expect.poll(() => urlParam(page, "sex")).toBe("M");
    expect(await rowNames(page)).toEqual([male.name]);

    // Reload: the filter comes from the URL and the panel starts open.
    await page.reload();
    await waitForTable(page);
    await expect(filtersToggle(page)).toHaveAttribute("aria-expanded", "true");
    await expect(sexFilter(page)).toHaveValue("M");
    expect(await rowNames(page)).toEqual([male.name]);

    // Each filter change is a history entry (push).
    await page.goBack();
    await expect.poll(() => urlParam(page, "sex")).toBe("F");
    await expect(sexFilter(page)).toHaveValue("F");
    expect(await rowNames(page)).toEqual([female.name]);

    await page.goBack();
    await expect.poll(() => urlParam(page, "sex")).toBeNull();
    await expect(sexFilter(page)).toHaveValue("");
    await expect(page.getByRole("button", { name: "Filtros", exact: true })).toBeVisible();
    expect(await rowNames(page)).toEqual(names([female, male]));
  });

  test("faixa etária: idades-limite no fuso America/Sao_Paulo (inclusive) e idade máxima 0", async ({ page, api }) => {
    const prefix = uniqueTag();
    // [label, years ago, day offset]. Born N years ago today = N; born N years ago tomorrow = N - 1.
    const specs = [
      ["aa", 30, 0], // 30: lower bound, included
      ["ab", 30, 1], // 29 (turns 30 tomorrow): excluded
      ["ac", 41, 1], // 40 (turns 41 tomorrow): upper bound, included
      ["ad", 41, 0], // 41: excluded
      ["ae", 0, 0], //  0: born today
      ["af", 1, 0], //  1
    ] as const;
    const patients: Patient[] = [];
    for (const [suffix, years, days] of specs) {
      const birth = birthDateYearsAgo(years, days);
      const patient = await api.createPatient({ name: `${prefix} ${suffix}`, birth_date: birth });
      // Fails here (not in the UI) if the API's age rule disagrees.
      expect(patient.age, `API age for ${birth}`).toBe(ageOn(birth));
      patients.push(patient);
    }
    const between = (min: number | null, max: number | null) =>
      names(patients.filter((p) => (min === null || p.age >= min) && (max === null || p.age <= max)));
    // Sanity: the boundary cases are where they should be (unless a leap day moved them).
    expect(patients.map((p) => p.age)).toEqual(specs.map(([, y, d]) => ageOn(birthDateYearsAgo(y, d))));

    await gotoListing(page, prefix);
    await expect(patientRows(page)).toHaveCount(6);
    await openFilters(page);

    // Enter applies the minimum
    await ageFilter(page, "Idade mínima").fill("30");
    await ageFilter(page, "Idade mínima").press("Enter");
    await expect.poll(() => urlParam(page, "age_min")).toBe("30");
    expect(await rowNames(page)).toEqual(between(30, null));

    // Blur applies the maximum
    await ageFilter(page, "Idade máxima").fill("40");
    await ageFilter(page, "Idade máxima").blur();
    await expect.poll(() => urlParam(page, "age_max")).toBe("40");
    expect(await rowNames(page)).toEqual(between(30, 40));
    expect(await rowNames(page)).toEqual(names([patients[0], patients[2]]));
    await expect(page.getByRole("button", { name: "Filtros (2 ativos)", exact: true })).toBeVisible();

    // Zero is a real bound (not "empty")
    await ageFilter(page, "Idade mínima").fill("");
    await ageFilter(page, "Idade máxima").fill("0");
    await ageFilter(page, "Idade máxima").press("Enter");
    await expect.poll(() => urlParam(page, "age_max")).toBe("0");
    expect(urlParam(page, "age_min")).toBeNull();
    expect(await rowNames(page)).toEqual(between(null, 0));
    expect(await rowNames(page)).toEqual([patients[4].name]);

    // The API agrees with the UI for the same range
    const res = await api.list({ search: prefix, age_min: 30, age_max: 40, per_page: 100 });
    expect(names((await res.json()).data)).toEqual(between(30, 40));
  });

  test("validação: idade fora de 0–150 e máxima menor que a mínima não aplicam o filtro", async ({ page }) => {
    const prefix = uniqueTag();
    await gotoListing(page, prefix); // no patients needed
    await openFilters(page);
    const min = ageFilter(page, "Idade mínima");
    const max = ageFilter(page, "Idade máxima");
    const clear = filtersPanel(page).getByRole("button", { name: "Limpar filtros" });
    await expect(clear).toHaveAttribute("aria-disabled", "true");

    await min.fill("151");
    await min.blur();
    await expect(min).toHaveAccessibleDescription("Informe uma idade entre 0 e 150.");
    await expect(min).toHaveAttribute("aria-invalid", "true");
    expect(urlParam(page, "age_min")).toBeNull();

    // A valid maximum alone applies (leaving the field applies it)...
    await min.fill("");
    await max.fill("40");
    await max.press("Enter");
    await expect.poll(() => urlParam(page, "age_max")).toBe("40");
    await expect(page.getByText("Informe uma idade entre 0 e 150.")).toHaveCount(0);
    // ...but a minimum above it is rejected, with the error on the maximum
    await min.fill("50");
    await min.press("Enter");
    await expect(max).toHaveAccessibleDescription("A idade máxima deve ser maior ou igual à mínima.");
    await expect(max).toHaveAttribute("aria-invalid", "true");
    await expect(min).not.toHaveAttribute("aria-invalid", "true");
    expect(urlParam(page, "age_min")).toBeNull();
    expect(urlParam(page, "age_max")).toBe("40");
    await expect(page.getByRole("button", { name: "Filtros (1 ativo)", exact: true })).toBeVisible();

    // Fixing the range applies both bounds
    await max.fill("60");
    await max.press("Enter");
    await expect.poll(() => urlParam(page, "age_max")).toBe("60");
    expect(urlParam(page, "age_min")).toBe("50");
    await expect(page.getByText("A idade máxima deve ser maior ou igual à mínima.")).toHaveCount(0);
    await expect(max).not.toHaveAttribute("aria-invalid", "true");

    // Limpar filtros: clears the URL and the inputs, keeps the search, focus stays in the panel
    await expect(clear).not.toHaveAttribute("aria-disabled", "true");
    await clear.click();
    await expect.poll(() => urlParam(page, "age_min")).toBeNull();
    expect(urlParam(page, "age_max")).toBeNull();
    expect(urlParam(page, "search")).toBe(prefix);
    await expect(min).toHaveValue("");
    await expect(max).toHaveValue("");
    await expect(sexFilter(page)).toBeFocused();
    await expect(clear).toHaveAttribute("aria-disabled", "true");
    await expect(page.getByRole("button", { name: "Filtros", exact: true })).toBeVisible();
  });

  test("combina com busca, ordenação e itens por página (sem ?page)", async ({ page, api }) => {
    const prefix = uniqueTag();
    const older = await api.createPatient({ name: `${prefix} aa`, sex: "F", birth_date: "1980-05-10" });
    const younger = await api.createPatient({ name: `${prefix} ab`, sex: "F", birth_date: "2000-05-10" });
    await api.createPatient({ name: `${prefix} ac`, sex: "M", birth_date: "1990-05-10" });

    await gotoListingQuery(page, { search: prefix, sort: "birth_date", direction: "desc", per_page: 25 });
    await expect(patientRows(page)).toHaveCount(3);
    await openFilters(page);
    await sexFilter(page).selectOption("F");

    await expect.poll(() => urlParam(page, "sex")).toBe("F");
    const url = new URL(page.url());
    expect(Object.fromEntries(url.searchParams)).toEqual({
      search: prefix,
      sort: "birth_date",
      direction: "desc",
      per_page: "25",
      sex: "F",
    });
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(2);
    // Sorted by birth date desc: the younger first
    await expect(patientRows(page).nth(0)).toContainText(younger.name);
    await expect(patientRows(page).nth(1)).toContainText(older.name);
    await expect(page.getByRole("columnheader", { name: "Data de nascimento" })).toHaveAttribute(
      "aria-sort",
      "descending"
    );
    await expect(page.getByRole("combobox", { name: "Itens por página" })).toHaveValue("25");

    // Typing a new search keeps the filters
    await page.getByRole("searchbox", { name: "Buscar por nome do paciente ou CPF" }).fill(older.name);
    await expect.poll(() => urlParam(page, "search")).toBe(older.name);
    expect(urlParam(page, "sex")).toBe("F");
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await expect(rowFor(page, older.name)).toBeVisible();
  });

  test("mudar um filtro na página 2 volta à página 1 (push) e mantém a ordenação", async ({ page, api }) => {
    const prefix = uniqueTag();
    await api.createMany(11, prefix); // all "F" (default): 11 matches still have a page 2

    await gotoListingQuery(page, { search: prefix, page: 2, sort: "birth_date", direction: "desc" });
    await expect(page.getByText("Mostrando 11–11 de 11", { exact: true })).toBeVisible();
    await openFilters(page);
    await sexFilter(page).selectOption("F");

    await expect.poll(() => urlParam(page, "sex")).toBe("F");
    expect(urlParam(page, "page")).toBeNull();
    expect(urlParam(page, "sort")).toBe("birth_date");
    expect(urlParam(page, "direction")).toBe("desc");
    await expect(page.getByText("Mostrando 1–10 de 11", { exact: true })).toBeVisible();

    await page.goBack();
    await expect.poll(() => urlParam(page, "page")).toBe("2");
    expect(urlParam(page, "sex")).toBeNull();
    await expect(page.getByText("Mostrando 11–11 de 11", { exact: true })).toBeVisible();
    await expect(sexFilter(page)).toHaveValue("");
  });

  test("estado vazio: textos com filtros (com e sem busca) e Exportar CSV desabilitado", async ({ page }) => {
    await gotoListingQuery(page, { search: uniqueTag(), sex: "M" });
    await expect(emptyState(page)).toBeVisible();
    await expect(page.getByText("Tente buscar por outro nome ou CPF ou ajuste os filtros.", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Exportar CSV" })).toBeDisabled();
    await expect(filtersToggle(page)).toHaveAttribute("aria-expanded", "true"); // opened by the URL

    // No realistic patient is 149+ years old
    await gotoListingQuery(page, { age_min: 149 });
    await expect(emptyState(page)).toBeVisible();
    await expect(
      page.getByText("Nenhum paciente corresponde aos filtros aplicados. Ajuste ou limpe os filtros.", { exact: true })
    ).toBeVisible();
    await expect(ageFilter(page, "Idade mínima")).toHaveValue("149");
    await expect(page.getByRole("menubar", { name: "Paginação" })).toHaveCount(0);

    // Invalid values in the URL are ignored (like the API)
    await gotoListingQuery(page, { age_min: "abc", sex: "X" });
    await expect(page.getByRole("button", { name: "Filtros", exact: true })).toHaveAttribute("aria-expanded", "false");
    await expect(patientRows(page).first()).toBeVisible();
  });
});
