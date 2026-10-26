import type { Page, Request } from "@playwright/test";
import { expect, field, test } from "../support/fixtures";
import { generateCpf, maskCpf } from "../support/data";

const CPF_TAKEN = "Já existe um paciente cadastrado com este CPF.";

/** Collects the CPF availability requests the page sends (and any patient POST/PUT). */
function recordRequests(page: Page): { availability: Request[]; writes: Request[] } {
  const recorded = { availability: [] as Request[], writes: [] as Request[] };
  page.on("request", (req) => {
    const path = new URL(req.url()).pathname;
    if (path.endsWith("/patients/cpf-availability")) recorded.availability.push(req);
    else if (/\/patients(\/\d+)?$/.test(path) && ["POST", "PUT"].includes(req.method())) recorded.writes.push(req);
  });
  return recorded;
}

function availabilityResponse(page: Page) {
  return page.waitForResponse((res) => new URL(res.url()).pathname.endsWith("/patients/cpf-availability"));
}

test.describe("Verificação de CPF disponível", () => {
  test("cadastro: CPF de paciente existente mostra erro ao sair do campo, sem enviar", async ({ page, api }) => {
    const existing = await api.createPatient();
    const requests = recordRequests(page);

    await page.goto("/pacientes/novo");
    const cpf = field(page, "CPF");
    await cpf.pressSequentially(existing.cpf);
    await expect(cpf).toHaveValue(maskCpf(existing.cpf));
    const checked = availabilityResponse(page);
    await cpf.blur();
    expect(new URL((await checked).url()).searchParams.get("cpf")?.replace(/\D/g, "")).toBe(existing.cpf);

    await expect(cpf).toHaveAccessibleDescription(CPF_TAKEN);
    await expect(cpf).toHaveAttribute("aria-describedby", /pf-cpf-error/);
    await expect(cpf).toHaveAttribute("aria-invalid", "true");
    expect(requests.writes).toHaveLength(0);
    await expect(page).toHaveURL(/\/pacientes\/novo$/);

    // Typing a free CPF clears the error.
    await cpf.fill("");
    await cpf.pressSequentially(generateCpf());
    await expect(cpf).not.toHaveAccessibleDescription(CPF_TAKEN);
    const rechecked = availabilityResponse(page);
    await cpf.blur();
    expect((await (await rechecked).json()).available).toBe(true);
    await expect(cpf).not.toHaveAccessibleDescription(CPF_TAKEN);
  });

  test("cadastro: CPF de paciente na lixeira está disponível", async ({ page, api }) => {
    const trashed = await api.createPatient();
    await api.deletePatient(trashed.id);

    await page.goto("/pacientes/novo");
    const cpf = field(page, "CPF");
    await cpf.pressSequentially(trashed.cpf);
    const checked = availabilityResponse(page);
    await cpf.blur();
    expect((await (await checked).json()).available).toBe(true);
    await expect(cpf).not.toHaveAccessibleDescription(CPF_TAKEN);
  });

  test("edição: o próprio CPF inalterado não é verificado; CPF de outro paciente mostra erro", async ({
    page,
    api,
  }) => {
    const other = await api.createPatient();
    const patient = await api.createPatient();
    const requests = recordRequests(page);

    await page.goto(`/pacientes/${patient.id}/editar`);
    const cpf = field(page, "CPF");
    await expect(cpf).toHaveValue(maskCpf(patient.cpf));

    await cpf.focus();
    await cpf.blur();
    // Longer than the 300ms debounce: a check would have been sent by now.
    await page.waitForTimeout(800);
    expect(requests.availability).toHaveLength(0);
    await expect(cpf).not.toHaveAccessibleDescription(CPF_TAKEN);

    // Another patient's CPF is flagged on blur, before submitting.
    await cpf.fill(maskCpf(other.cpf));
    const checked = availabilityResponse(page);
    await cpf.blur();
    const response = await checked;
    expect(new URL(response.url()).searchParams.get("ignore")).toBe(String(patient.id));
    await expect(cpf).toHaveAccessibleDescription(CPF_TAKEN);
    expect(requests.writes).toHaveLength(0);

    // Back to the patient's own CPF: error cleared, no new check.
    const before = requests.availability.length;
    await cpf.fill(maskCpf(patient.cpf));
    await cpf.blur();
    await page.waitForTimeout(800);
    await expect(cpf).not.toHaveAccessibleDescription(CPF_TAKEN);
    expect(requests.availability).toHaveLength(before);
  });
});
