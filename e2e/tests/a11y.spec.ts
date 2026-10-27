import AxeBuilder from "@axe-core/playwright";
import type { Page } from "@playwright/test";
import {
  addDaysIso,
  agendaItems,
  deleteDialog,
  detailValue,
  expect,
  field,
  gotoAgenda,
  gotoConsultation,
  gotoListing,
  gotoTrash,
  randomFutureMonday,
  rowFor,
  statusDialog,
  test,
  toast,
  trashRows,
  waitForFadeIn,
  waitForTable,
} from "../support/fixtures";
import type { Consultation, ConsultationStatus, Patient, PatientApi } from "../support/api";
import { professionalName } from "../support/data";
import { CEP_UNAVAILABLE_BODY, examPdf, mockCep, photoPng } from "../support/files";

const WCAG_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"];

/** Runs axe on the current page and fails with a readable list of violations. */
async function expectNoA11yViolations(page: Page): Promise<void> {
  const { violations } = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
  const summary = violations.map(
    (v) => `${v.id} (${v.impact}): ${v.help}\n  ${v.nodes.map((n) => n.target.join(" ")).join("\n  ")}`
  );
  expect(summary, "axe violations").toEqual([]);
}

/** A consultation (far-future week) for `patient`, moved to `status`. */
async function createConsultation(
  api: PatientApi,
  patient: Patient,
  status: ConsultationStatus = "scheduled",
  scheduledAt = `${addDaysIso(randomFutureMonday(), 1)}T14:30:00-03:00`
): Promise<Consultation> {
  const c = await api.createConsultation(patient.id, {
    scheduled_at: scheduledAt,
    professional_name: professionalName("Dra."),
    professional_crm: "CRM-SP 12345",
    reason: "Visão embaçada para perto.",
  });
  return status === "scheduled" ? c : api.setStatus(c.id, status);
}

const REFRACTION = {
  od: { visual_acuity_sc: "20/40", spherical: -1.25, cylindrical: -0.5, axis: 180, iop: 14 },
  oe: { spherical: -1, addition: 2, iop: 15 },
  diagnosis: "Miopia leve com astigmatismo.",
};

for (const viewport of [
  { name: "desktop", width: 1280, height: 900 },
  { name: "mobile", width: 375, height: 812 },
]) {
  test.describe(`Acessibilidade (axe, WCAG 2.1 AA) @ ${viewport.name}`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    // Created only by the tests that need it (the API rate limit is tight for the whole suite).
    const createPatient = (api: PatientApi): Promise<Patient> =>
      api.createPatient({ email: "e2e@example.com", description: "Paciente criado pelo E2E" });

    test("listagem", async ({ page, api }) => {
      await createPatient(api); // at least one row
      await gotoListing(page);
      await expect(page.getByRole("table", { name: "Lista de pacientes" }).getByRole("row").nth(1)).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("listagem ordenada", async ({ page, api }) => {
      await createPatient(api); // at least one row
      await page.goto("/?sort=birth_date&direction=desc");
      await waitForTable(page);
      await expect(page.getByRole("columnheader", { name: "Data de nascimento" })).toHaveAttribute(
        "aria-sort",
        "descending"
      );
      await expect(page.getByRole("table", { name: "Lista de pacientes" }).getByRole("row").nth(1)).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("listagem com filtros abertos e erro de validação", async ({ page }) => {
      // ?sex opens the panel; no patients needed (the empty state is checked too).
      await page.goto(`/?sex=F&search=${encodeURIComponent("E2E sem resultado a11y")}`);
      await waitForTable(page);
      const min = page.getByRole("spinbutton", { name: "Idade mínima", exact: true });
      await expect(min).toBeVisible();
      await min.fill("151");
      await min.blur();
      await expect(min).toHaveAccessibleDescription("Informe uma idade entre 0 e 150.");
      await expectNoA11yViolations(page);
    });

    test("detalhes: aba Histórico", async ({ page, api }) => {
      const patient = await createPatient(api);
      await api.updatePatient(patient, { name: `${patient.name} Editado` });
      await page.goto(`/pacientes/${patient.id}?aba=historico`);
      await expect(page.getByText("Carregando histórico...")).toHaveCount(0);
      const panel = page.getByRole("tabpanel", { name: "Histórico" });
      await expect(panel.getByRole("list", { name: "Histórico de alterações" }).locator(":scope > li")).toHaveCount(2);
      await waitForFadeIn(panel);
      await expectNoA11yViolations(page);
    });

    test("lixeira com pacientes", async ({ page, api }) => {
      const patient = await createPatient(api);
      await api.deletePatient(patient.id);
      await gotoTrash(page);
      await expect(trashRows(page).first()).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("detalhes", async ({ page, api }) => {
      const patient = await createPatient(api);
      await page.goto(`/pacientes/${patient.id}`);
      await expect(detailValue(page, "Nome completo")).toHaveText(patient.name);
      await expectNoA11yViolations(page);
    });

    test("cadastro", async ({ page }) => {
      await page.goto("/pacientes/novo");
      await expect(page.getByRole("heading", { level: 1, name: "Cadastro de novo paciente" })).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("cadastro com erros", async ({ page }) => {
      await page.goto("/pacientes/novo");
      await page.getByRole("button", { name: "Salvar paciente" }).click();
      await expect(field(page, "Nome completo")).toHaveAccessibleDescription("Campo obrigatório");
      await expectNoA11yViolations(page);
    });

    test("cadastro com erros no endereço e aviso de CEP indisponível", async ({ page }) => {
      await mockCep(page, 503, CEP_UNAVAILABLE_BODY);
      await page.goto("/pacientes/novo");
      await page.locator("#pf-address-cep").pressSequentially("01310100");
      await expect(page.locator("#pf-address-cep-note")).toBeVisible();
      await page.locator("#pf-address-street").fill("Rua Parcial");
      await page.getByRole("button", { name: "Salvar paciente" }).click();
      await expect(page.locator("#pf-address-number")).toHaveAccessibleDescription("Campo obrigatório");
      await expect(page.locator("#pf-address-state")).toHaveAccessibleDescription("Campo obrigatório");
      // Let the error toast finish fading in (axe would measure the contrast mid-transition).
      const message = toast(page, "Preencha os campos obrigatórios para salvar!");
      await expect(message).toBeVisible();
      await expect.poll(() => message.evaluate((el) => getComputedStyle(el.closest(".toast") ?? el).opacity)).toBe("1");
      await expectNoA11yViolations(page);
    });

    test("detalhes com foto e modal de remover foto", async ({ page, api }) => {
      const patient = await createPatient(api);
      await api.uploadPhoto(patient.id, photoPng());
      await page.goto(`/pacientes/${patient.id}`);
      const img = page.getByRole("img", { name: `Foto de ${patient.name}` });
      await expect.poll(() => img.evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0)).toBe(true);
      await expect(page.locator("#patient-photo-pick")).toHaveText("Trocar foto");
      await expectNoA11yViolations(page);

      await page.locator("#patient-photo-remove").click();
      await waitForFadeIn(page.getByRole("dialog", { name: "Remover foto" }));
      await expectNoA11yViolations(page);
    });

    test("atendimento com anexos e modal de exclusão de anexo", async ({ page, api }) => {
      const consultation = await createConsultation(api, await createPatient(api));
      await api.uploadAttachment(consultation.id, examPdf("laudo.pdf"), "Topografia de córnea");
      await api.uploadAttachment(consultation.id, photoPng("retina.png"));
      await gotoConsultation(page, consultation.id);
      await expect(page.locator(".attachment-item")).toHaveCount(2);
      // Client-side error state of the upload form too.
      await page.getByRole("button", { name: "Enviar anexo" }).click();
      await expect(page.locator("#attachment-file-error")).toHaveText("Selecione um arquivo.");
      await expectNoA11yViolations(page);

      await page.getByRole("button", { name: "Excluir anexo laudo.pdf" }).click();
      await waitForFadeIn(page.getByRole("dialog", { name: "Excluir anexo" }));
      await expectNoA11yViolations(page);
    });

    test("edição", async ({ page, api }) => {
      const patient = await createPatient(api);
      await page.goto(`/pacientes/${patient.id}/editar`);
      await expect(field(page, "Nome completo")).toHaveValue(patient.name);
      await expectNoA11yViolations(page);
    });

    test("modal de exclusão", async ({ page, api }) => {
      const patient = await createPatient(api);
      await gotoListing(page, patient.name);
      await rowFor(page, patient.name).getByRole("button", { name: `Excluir ${patient.name}` }).click();
      await waitForFadeIn(deleteDialog(page));
      await expectNoA11yViolations(page);
    });

    test("agenda com atendimentos e modal de cancelamento", async ({ page, api }) => {
      const patient = await createPatient(api);
      const consultation = await createConsultation(api, patient);
      const day = consultation.scheduled_at.slice(0, 10);
      await createConsultation(api, patient, "cancelled", `${day}T16:00:00-03:00`); // another badge color
      await gotoAgenda(page, { semana: day });
      // (other tests may have rows in the same random week: count only this patient's)
      await expect(agendaItems(page).filter({ hasText: patient.name })).toHaveCount(2);
      await expectNoA11yViolations(page);

      await page.getByRole("button", { name: `Cancelar atendimento: atendimento de ${patient.name} às 14:30` }).click();
      await waitForFadeIn(statusDialog(page, "Cancelar atendimento"));
      await expectNoA11yViolations(page);
    });

    test("novo atendimento com erros", async ({ page, api }) => {
      const patient = await createPatient(api);
      await page.goto(`/pacientes/${patient.id}/atendimentos/novo`);
      await expect(page.getByRole("heading", { level: 1, name: "Novo atendimento" })).toBeVisible();
      await expectNoA11yViolations(page);
      await page.locator("#cf-date").fill("");
      await page.getByRole("button", { name: "Agendar atendimento" }).click();
      await expect(page.locator("#cf-professional")).toHaveAccessibleDescription("Campo obrigatório");
      await expectNoA11yViolations(page);
    });

    test("atendimento agendado", async ({ page, api }) => {
      const consultation = await createConsultation(api, await createPatient(api));
      await gotoConsultation(page, consultation.id);
      await expect(page.getByRole("button", { name: "Confirmar" })).toBeVisible();
      await expectNoA11yViolations(page);
      await page.getByRole("button", { name: "Editar atendimento" }).click();
      await expect(page.locator("#cf-time")).toHaveValue("14:30");
      await expectNoA11yViolations(page);
    });

    test("atendimento realizado: formulário do prontuário (com erros) e prontuário somente leitura", async ({
      page,
      api,
    }) => {
      const consultation = await createConsultation(api, await createPatient(api), "completed");
      await gotoConsultation(page, consultation.id);
      await expect(page.locator("#ee-od-spherical")).toBeVisible();
      await expectNoA11yViolations(page);

      await page.locator("#ee-od-iop").fill("99");
      await page.getByRole("button", { name: "Salvar prontuário" }).click();
      await expect(page.locator("#ee-od-iop-error")).toBeVisible();
      await expectNoA11yViolations(page);

      await api.saveEyeExam(consultation.id, REFRACTION);
      await page.reload();
      await expect(page.getByRole("button", { name: "Baixar receita (PDF)" })).toBeVisible();
      await expectNoA11yViolations(page);
    });

    test("detalhes: abas Atendimentos e Linha do tempo", async ({ page, api }) => {
      const patient = await createPatient(api);
      const consultation = await createConsultation(api, patient, "completed");
      await api.saveEyeExam(consultation.id, REFRACTION);

      await page.goto(`/pacientes/${patient.id}?aba=atendimentos`);
      const consultations = page.getByRole("tabpanel", { name: "Atendimentos" });
      await expect(consultations.getByRole("list", { name: "Atendimentos do paciente" })).toBeVisible();
      await expect(consultations.getByText("Prontuário registrado")).toBeVisible();
      await waitForFadeIn(consultations);
      await expectNoA11yViolations(page);

      await page.getByRole("tab", { name: "Linha do tempo" }).click();
      const timeline = page.getByRole("tabpanel", { name: "Linha do tempo" });
      await expect(timeline.getByRole("list", { name: "Linha do tempo do paciente" })).toBeVisible();
      await expect(timeline.getByText(/^Refração:/)).toBeVisible();
      await waitForFadeIn(timeline);
      await expectNoA11yViolations(page);
    });

    test("paciente não encontrado", async ({ page, api }) => {
      const patient = await createPatient(api);
      await api.deletePatient(patient.id);
      await page.goto(`/pacientes/${patient.id}`);
      await expect(page.getByRole("heading", { level: 1, name: "Paciente não encontrado" })).toBeVisible();
      await expectNoA11yViolations(page);
    });
  });
}
