/**
 * UI regression tests for the audit fixes (fix/audit-findings). Each test says why it would fail on the
 * behavior before the fix. Arrangement goes through the API helper and is kept minimal (shared rate limit).
 */
import type { Page } from "@playwright/test";
import {
  addDaysIso,
  agendaItems,
  deleteDialog,
  expect,
  field,
  gotoAgenda,
  gotoListing,
  gotoTrash,
  historyChange,
  historyEntries,
  openFilters,
  patientRows,
  randomFutureMonday,
  rowFor,
  searchBox,
  sexFilter,
  test,
  toast,
  todaySaoPaulo,
  trashRows,
  urlParam,
  waitForFadeIn,
  waitForHistory,
  waitForTable,
  waitForTrashTable,
} from "../support/fixtures";
import { isoToBr, professionalName, tokenOf, uniqueTag } from "../support/data";
import { photoPng } from "../support/files";
import type { Patient, PatientApi } from "../support/api";

const RESTORED_WITH_CANCEL =
  "Paciente restaurado com sucesso! 1 atendimento futuro foi cancelado por conflito de horário.";

const scrollY = (page: Page) => page.evaluate(() => Math.round(window.scrollY));

/* ---------- #5 search typed just before another list action ---------- */

test.describe("#5 busca digitada e outra ação dentro do debounce (400 ms)", () => {
  // Before the fix a push (sort/per-page/filter/page) wrote the OLD search (still in the URL, the debounce not
  // fired yet) and the route watcher then reset the input to it: the typed text was lost from both.
  test("listagem: ordenar, itens por página e filtro mantêm o texto digitado na URL e no campo", async ({
    page,
    api,
  }) => {
    const prefix = uniqueTag();
    const aa = await api.createPatient({ name: `${prefix} aa`, sex: "F" });
    const ab = await api.createPatient({ name: `${prefix} ab`, sex: "M" });
    await gotoListing(page, prefix);
    await expect(patientRows(page)).toHaveCount(2);

    // Sort header
    await searchBox(page).fill(ab.name);
    await page.getByRole("button", { name: "Nome do paciente" }).click();
    await expect.poll(() => urlParam(page, "direction")).toBe("desc");
    expect(urlParam(page, "search")).toBe(ab.name);
    await expect(searchBox(page)).toHaveValue(ab.name);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await page.waitForTimeout(600); // past the debounce: nothing reverts it
    await expect(searchBox(page)).toHaveValue(ab.name);
    expect(urlParam(page, "search")).toBe(ab.name);

    // Per page
    await searchBox(page).fill(aa.name);
    await page.getByRole("combobox", { name: "Itens por página" }).selectOption("25");
    await expect.poll(() => urlParam(page, "per_page")).toBe("25");
    expect(urlParam(page, "search")).toBe(aa.name);
    await expect(searchBox(page)).toHaveValue(aa.name);
    await waitForTable(page);
    await expect(rowFor(page, aa.name)).toBeVisible();
    await expect(patientRows(page)).toHaveCount(1);

    // Filter (panel opened first, so only the select happens within the debounce)
    await openFilters(page);
    await searchBox(page).fill(prefix);
    await sexFilter(page).selectOption({ label: "Masculino" });
    await expect.poll(() => urlParam(page, "sex")).toBe("M");
    expect(urlParam(page, "search")).toBe(prefix);
    await expect(searchBox(page)).toHaveValue(prefix);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(1);
    await expect(rowFor(page, ab.name)).toBeVisible();
    await page.waitForTimeout(600);
    await expect(searchBox(page)).toHaveValue(prefix);
  });

  test("lixeira: Próxima página logo após digitar aplica a busca nova (página 1) e mantém o texto", async ({
    page,
    api,
  }) => {
    const prefix = uniqueTag();
    const patients = await api.createMany(11, prefix);
    for (const p of patients) await api.deletePatient(p.id);

    await gotoTrash(page, prefix);
    await expect(trashRows(page)).toHaveCount(10);
    const last = patients[10];
    await searchBox(page).fill(last.name);
    await page.getByRole("menubar", { name: "Paginação" }).getByRole("menuitem", { name: "Próxima página" }).click();
    await expect.poll(() => urlParam(page, "search")).toBe(last.name);
    // New term: page numbers of the old one no longer apply.
    expect(urlParam(page, "page")).toBeNull();
    await expect(searchBox(page)).toHaveValue(last.name);
    await waitForTrashTable(page);
    await expect(trashRows(page)).toHaveCount(1);
    await expect(rowFor(page, last.name)).toBeVisible();
    await page.waitForTimeout(600);
    await expect(searchBox(page)).toHaveValue(last.name);
  });
});

/* ---------- #6 history/timeline refresh after a photo change ---------- */

test("#6 enviar e remover foto atualiza as abas Histórico e Linha do tempo já abertas, sem recarregar", async ({
  page,
  api,
}) => {
  // Both tabs are mounted once and kept. Before the fix nothing told them the photo changed, so an already opened
  // tab kept showing the old list until a reload. They are opened BEFORE the upload on purpose: a tab first
  // opened after the upload would load fresh and pass on the old code too.
  const patient = await api.createPatient();
  await page.goto(`/pacientes/${patient.id}`);
  await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();

  const timeline = page.getByRole("list", { name: "Linha do tempo do paciente" });
  const timelinePhoto = (summary: string) =>
    timeline.locator(".timeline-change").filter({
      has: page.locator(".timeline-change__field", { hasText: /^Foto$/ }),
      hasText: summary,
    });

  await page.getByRole("tab", { name: "Histórico" }).click();
  await waitForHistory(page);
  await expect(historyEntries(page)).toHaveCount(1); // created
  await page.getByRole("tab", { name: "Linha do tempo" }).click();
  await expect(page.getByText("Carregando linha do tempo...")).toHaveCount(0);
  await expect(timeline).toBeVisible();
  await expect(timelinePhoto("Adicionada")).toHaveCount(0);

  // Upload from the header (visible on every tab).
  const chooser = page.waitForEvent("filechooser");
  await page.locator("#patient-photo-pick").click();
  await (await chooser).setFiles(photoPng());
  const preview = page.getByRole("dialog", { name: "Enviar foto" });
  await waitForFadeIn(preview);
  await preview.getByRole("button", { name: "Enviar foto" }).click();
  await expect(toast(page, "Foto atualizada com sucesso!")).toBeVisible();

  await expect(timelinePhoto("Adicionada")).toHaveCount(1);
  await page.getByRole("tab", { name: "Histórico" }).click();
  await expect(historyEntries(page)).toHaveCount(2);
  await expect(historyChange(historyEntries(page).first(), "Foto")).toContainText("Foto: Adicionada");

  // Remove, while the history tab is the one shown.
  await page.locator("#patient-photo-remove").click();
  const removeDialog = page.getByRole("dialog", { name: "Remover foto" });
  await waitForFadeIn(removeDialog);
  await removeDialog.getByRole("button", { name: "Confirmar" }).click();
  await expect(toast(page, "Foto removida com sucesso!")).toBeVisible();
  await expect(historyEntries(page)).toHaveCount(3);
  await expect(historyChange(historyEntries(page).first(), "Foto")).toContainText("Foto: Removida");
  await page.getByRole("tab", { name: "Linha do tempo" }).click();
  await expect(timelinePhoto("Removida")).toHaveCount(1);
});

/* ---------- #7 document title ---------- */

test("#7 título '<nome> | EyeCare' se mantém ao trocar de aba e no voltar/avançar", async ({ page, api }) => {
  // The tab switch is the discriminating step: it is a query-only router.replace, and before the fix afterEach
  // set the route's meta title ("Paciente | EyeCare") on every navigation, overwriting the name. Back/Forward
  // between listing and detail is a sanity check only (the detail page re-sets the name when it loads anyway).
  const patient = await api.createPatient();
  const title = `${patient.name} | EyeCare`;
  await gotoListing(page, patient.name);
  await expect(page).toHaveTitle("Pacientes | EyeCare");
  await page.getByRole("link", { name: `Ver detalhes de ${patient.name}` }).click();
  await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
  await expect(page).toHaveTitle(title);

  for (const tab of ["Histórico", "Atendimentos", "Linha do tempo", "Dados"]) {
    await page.getByRole("tab", { name: tab }).click();
    await expect(page.getByRole("tab", { name: tab })).toHaveAttribute("aria-selected", "true");
    await expect(page).toHaveTitle(title);
  }
  await page.getByRole("tab", { name: "Histórico" }).click();
  await expect(page).toHaveURL(/aba=historico/);
  await expect(page).toHaveTitle(title);

  await page.goBack();
  await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
  await expect(page).toHaveTitle("Pacientes | EyeCare");
  await page.goForward();
  await expect(page.getByRole("heading", { level: 1, name: patient.name })).toBeVisible();
  await expect(page.getByRole("tab", { name: "Histórico" })).toHaveAttribute("aria-selected", "true");
  await expect(page).toHaveTitle(title);
});

/* ---------- #8 scroll position ---------- */

test.describe("#8 posição de rolagem (mobile)", () => {
  test.use({ viewport: { width: 375, height: 600 }, isMobile: true, hasTouch: true });

  test("voltar dos detalhes restaura a rolagem da listagem; trocar de aba não volta ao topo", async ({
    page,
    api,
  }) => {
    // Before the fix scrollBehavior always returned {x: 0, y: 0}: Back to the listing landed at the top, and a
    // tab switch (query-only replace) jumped to the top as well.
    const prefix = uniqueTag();
    const patients = await api.createMany(12, prefix);
    await page.goto(`/?search=${encodeURIComponent(prefix)}&per_page=50`);
    await waitForTable(page);
    await expect(patientRows(page)).toHaveCount(12);
    const { height, inner } = await page.evaluate(() => ({
      height: document.documentElement.scrollHeight,
      inner: window.innerHeight,
    }));
    expect(height, "the listing must scroll for this test to mean something").toBeGreaterThan(inner + 400);

    const target = patients[9];
    const link = page.getByRole("link", { name: `Ver detalhes de ${target.name}` });
    await link.evaluate((el) => el.scrollIntoView({ block: "center" }));
    await expect.poll(() => scrollY(page)).toBeGreaterThan(300);
    const saved = await scrollY(page);
    await link.click();
    await expect(page.getByRole("heading", { level: 1, name: target.name })).toBeVisible();

    // Tab switch on the detail page keeps the position.
    const tabs = page.getByRole("tablist");
    await tabs.evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 8));
    await expect.poll(() => scrollY(page)).toBeGreaterThan(100);
    const beforeTab = await scrollY(page);
    await page.getByRole("tab", { name: "Atendimentos" }).click();
    await expect(page).toHaveURL(/aba=atendimentos/);
    await page.waitForTimeout(300);
    const afterTab = await scrollY(page);
    // The "Atendimentos" tab is shorter than "Dados", so the browser may clamp the position to the new maximum;
    // what matters is that it is not reset to the top (the old behavior: 0).
    const maxScroll = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
    expect(afterTab, `tab switch moved the page from ${beforeTab} to ${afterTab} (max ${maxScroll})`).toBeGreaterThanOrEqual(
      Math.min(beforeTab, maxScroll) - 5
    );
    expect(afterTab).toBeGreaterThan(0);
    await expect(page.getByRole("tab", { name: "Atendimentos" })).toBeInViewport();

    await page.goBack();
    await expect(page.getByRole("heading", { level: 1, name: "Pacientes", exact: true })).toBeVisible();
    await waitForTable(page);
    await expect.poll(() => scrollY(page)).toBeGreaterThan(saved - 40);
    expect(Math.abs((await scrollY(page)) - saved)).toBeLessThan(40);
    // (The actions column may be scrolled out horizontally inside the responsive table: check the name cell.)
    await expect(rowFor(page, target.name).getByRole("cell", { name: target.name, exact: true })).toBeInViewport();
  });
});

/* ---------- #12 trash focus ---------- */

test("#12 lixeira: depois de Restaurar o foco vai para o Restaurar da próxima linha", async ({ page, api }) => {
  // Before the fix the restore reloaded the whole table: the clicked button disappeared and focus fell to <body>.
  const prefix = uniqueTag();
  const [first, second, third] = await api.createMany(3, prefix);
  for (const p of [first, second, third]) await api.deletePatient(p.id);

  await gotoTrash(page, prefix);
  await expect(trashRows(page)).toHaveCount(3);
  await page.getByRole("button", { name: `Restaurar ${first.name}` }).click();
  await expect(toast(page, "Paciente restaurado com sucesso!")).toBeVisible();
  api.track(first.id);
  await expect(trashRows(page)).toHaveCount(2);
  await expect(page.getByRole("button", { name: `Restaurar ${second.name}` })).toBeFocused();

  // Last row: focus goes to the previous one.
  await page.getByRole("button", { name: `Restaurar ${third.name}` }).click();
  await expect(trashRows(page)).toHaveCount(1);
  api.track(third.id);
  await expect(page.getByRole("button", { name: `Restaurar ${second.name}` })).toBeFocused();
  await expect(page.getByText("Mostrando 1–1 de 1", { exact: true })).toBeVisible();
});

/* ---------- #13 agenda with a status filter ---------- */

test("#13 agenda filtrada por Agendado: Confirmar tira o item da lista e o foco vai para o próximo", async ({
  page,
  api,
}) => {
  // Before the fix the confirmed consultation stayed in a list filtered by "Agendado" (showing "Confirmado").
  const patient = await api.createPatient();
  const professional = professionalName();
  const day = addDaysIso(randomFutureMonday(), 1);
  const first = await api.createConsultation(patient.id, {
    scheduled_at: `${day}T09:00:00-03:00`,
    professional_name: professional,
  });
  const second = await api.createConsultation(patient.id, {
    scheduled_at: `${day}T10:00:00-03:00`,
    professional_name: professional,
  });

  await gotoAgenda(page, { semana: day, status: "scheduled", profissional: tokenOf(professional) });
  await expect(agendaItems(page)).toHaveCount(2);
  await page.getByRole("button", { name: `Confirmar: atendimento de ${patient.name} às 09:00` }).click();
  await expect(toast(page, "Atendimento confirmado.")).toBeVisible();
  await expect(agendaItems(page)).toHaveCount(1);
  await expect(page.locator(`#agenda-item-${first.id}`)).toHaveCount(0);
  await expect(page.locator(`#agenda-item-${second.id}`)).toBeFocused();
  expect((await (await api.getConsultation(first.id)).json()).data.status).toBe("confirmed");

  // Last item of the day: focus goes to the day heading.
  await page.getByRole("button", { name: `Confirmar: atendimento de ${patient.name} às 10:00` }).click();
  await expect(agendaItems(page)).toHaveCount(0);
  await expect(page.locator(`#agenda-day-${day}`)).toBeFocused();
});

/* ---------- #14 birth date "today" in São Paulo ---------- */

test.describe("#14 data de nascimento futura usa o dia de São Paulo", () => {
  test.use({ timezoneId: "Asia/Tokyo" });

  test("navegador em Tóquio: hoje de SP é aceito e amanhã de SP é recusado", async ({ page }) => {
    // Clock pinned at 15:00 in São Paulo = 03:00 of the NEXT day in Tokyo (12 h ahead). Before the fix "today" was
    // the browser's local date (Tokyo), so SP-tomorrow was accepted. Without the pin the test would only
    // discriminate when SP time is past 12:00 (before that both zones share the date).
    const today = todaySaoPaulo();
    const tomorrow = addDaysIso(today, 1);
    await page.clock.setFixedTime(new Date(`${today}T15:00:00-03:00`));
    await page.goto("/pacientes/novo");
    await expect(page.getByRole("heading", { level: 1, name: "Cadastro de novo paciente" })).toBeVisible();
    expect(await page.evaluate(() => new Date().getDate())).toBe(Number(tomorrow.slice(8, 10)));

    const birth = field(page, "Data de nascimento");
    const save = page.getByRole("button", { name: "Salvar paciente" });

    await birth.pressSequentially(isoToBr(tomorrow).replace(/\//g, ""));
    await expect(birth).toHaveValue(isoToBr(tomorrow));
    await save.click();
    await expect(birth).toHaveAccessibleDescription("Data não pode ser futura");

    await birth.fill("");
    await birth.pressSequentially(isoToBr(today).replace(/\//g, ""));
    await expect(birth).toHaveValue(isoToBr(today));
    await save.click();
    // Other required fields are empty: the form was validated again, and the birth date has no error.
    await expect(field(page, "Nome completo")).toHaveAccessibleDescription("Campo obrigatório");
    await expect(birth).not.toHaveAccessibleDescription(/futura/);
    await expect(birth).not.toHaveAttribute("aria-invalid", "true");
  });
});

/* ---------- Restore toast mentions cancelled consultations ---------- */

/** Patient with a future consultation; trashed; `other` then books the same slot and professional. */
async function arrangeConflict(api: PatientApi, other: Patient, hour: string): Promise<Patient> {
  const professional = professionalName();
  const slot = `${addDaysIso(randomFutureMonday(), 3)}T${hour}:00-03:00`;
  const patient = await api.createPatient();
  await api.createConsultation(patient.id, { scheduled_at: slot, professional_name: professional });
  await api.deletePatient(patient.id);
  await api.createConsultation(other.id, { scheduled_at: slot, professional_name: professional });
  return patient;
}

test("restaurar com conflito: toast avisa do atendimento cancelado (lixeira e Desfazer)", async ({ page, api }) => {
  // Before the fix the restore never cancelled anything and the toast was always just "Paciente restaurado com
  // sucesso!" (the exact-text toast() locator would not match).
  const other = await api.createPatient();

  // Trash page
  const trashed = await arrangeConflict(api, other, "09:00");
  await gotoTrash(page, trashed.name);
  await page.getByRole("button", { name: `Restaurar ${trashed.name}` }).click();
  await expect(toast(page, RESTORED_WITH_CANCEL)).toBeVisible();
  api.track(trashed.id);

  // "Desfazer": deleted through the UI, slot taken meanwhile, then undone.
  const professional = professionalName();
  const slot = `${addDaysIso(randomFutureMonday(), 4)}T11:00:00-03:00`;
  const undone = await api.createPatient();
  await api.createConsultation(undone.id, { scheduled_at: slot, professional_name: professional });
  await gotoListing(page, undone.name);
  await rowFor(page, undone.name).getByRole("button", { name: `Excluir ${undone.name}` }).click();
  await deleteDialog(page).getByRole("button", { name: "Confirmar" }).click();
  await expect(toast(page, "Paciente excluído com sucesso!")).toBeVisible();
  await api.createConsultation(other.id, { scheduled_at: slot, professional_name: professional });
  await page.getByRole("button", { name: `Desfazer exclusão de ${undone.name}` }).click();
  await expect(toast(page, RESTORED_WITH_CANCEL)).toBeVisible();
  expect((await api.getPatient(undone.id)).status()).toBe(200);
});

