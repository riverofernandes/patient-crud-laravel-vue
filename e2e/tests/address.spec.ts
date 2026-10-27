import type { Page, Request } from "@playwright/test";
import {
  detailValue,
  expect,
  field,
  historyChange,
  historyEntries,
  test,
  toast,
  waitForHistory,
} from "../support/fixtures";
import type { Patient, PatientApi } from "../support/api";
import { buildAddress, generateCpf, uniqueTag } from "../support/data";
import { CEP_FOUND_BODY, CEP_NOT_FOUND_BODY, CEP_UNAVAILABLE_BODY, mockCep } from "../support/files";

const ADDRESS_LINE = "Avenida Paulista, 1578 - Sala 12 - Bela Vista · São Paulo/SP · CEP 01310-100";

const uf = (page: Page) => page.getByRole("combobox", { name: "UF", exact: true });
const addressError = (page: Page, key: string) => page.locator(`#pf-address-${key}-error`);
const politeStatus = (page: Page, text: string) => page.getByRole("status").filter({ hasText: text });

/** Opens the edit form of `patient` and waits for it to be filled in. */
async function gotoEdit(page: Page, patient: Patient): Promise<void> {
  await page.goto(`/pacientes/${patient.id}/editar`);
  await expect(page.getByRole("heading", { level: 1, name: "Editar paciente" })).toBeVisible();
  await expect(field(page, "Nome completo")).toHaveValue(patient.name);
  await expect(page.getByRole("heading", { level: 2, name: "Endereço (Opcional)" })).toBeVisible();
}

/** Collects the PUT /patients/{id} requests sent by the page. */
function collectPuts(page: Page, id: number): Request[] {
  const puts: Request[] = [];
  page.on("request", (req) => {
    if (req.method() === "PUT" && new URL(req.url()).pathname.endsWith(`/patients/${id}`)) puts.push(req);
  });
  return puts;
}

async function saveEdit(page: Page, id: number): Promise<Record<string, unknown>> {
  const put = page.waitForResponse(
    (res) => res.request().method() === "PUT" && new URL(res.url()).pathname.endsWith(`/patients/${id}`)
  );
  await page.getByRole("button", { name: "Atualizar paciente" }).click();
  const response = await put;
  expect(response.status()).toBe(200);
  await expect(toast(page, "Paciente editado com sucesso!")).toBeVisible();
  return response.request().postDataJSON();
}

async function addressOf(api: PatientApi, id: number): Promise<Patient["address"]> {
  return (await (await api.getPatient(id)).json()).data.address;
}

test.describe("Endereço do paciente", () => {
  test("cadastro: CEP encontrado preenche o endereço, foca o Número e salva; detalhes mostram o endereço", async ({
    page,
    api,
  }) => {
    const calls = await mockCep(page, 200, CEP_FOUND_BODY);
    const name = `${uniqueTag()} Endereco`;
    await page.goto("/pacientes/novo");
    await expect(page.getByRole("heading", { level: 2, name: "Endereço (Opcional)" })).toBeVisible();
    await expect(page.getByText("Ao informar o endereço, só o complemento pode ficar em branco.")).toBeVisible();
    await expect(uf(page)).toHaveValue("");
    await expect(uf(page).locator("option:checked")).toHaveText("Selecionar");

    await field(page, "Nome completo").fill(name);
    await field(page, "CPF").pressSequentially(generateCpf());
    await field(page, "RG").fill("123456789");
    await field(page, "Data de nascimento").pressSequentially("01011990");
    await page.getByRole("combobox", { name: "Sexo biológico" }).selectOption({ label: "Feminino" });
    await field(page, "Celular").pressSequentially("11987654321");

    await field(page, "CEP").pressSequentially("01310100");
    await expect(field(page, "CEP")).toHaveValue("01310-100");
    await expect(field(page, "Rua")).toHaveValue("Avenida Paulista");
    await expect(field(page, "Bairro")).toHaveValue("Bela Vista");
    await expect(field(page, "Cidade")).toHaveValue("São Paulo");
    await expect(uf(page)).toHaveValue("SP");
    await expect(field(page, "Número")).toBeFocused();
    await expect(politeStatus(page, "Endereço encontrado")).toHaveCount(1);
    expect(calls).toEqual(["01310100"]);
    // Blur with the same CEP does not look it up again.
    await field(page, "CEP").focus();
    await field(page, "CEP").blur();

    await field(page, "Número").fill("1578");
    await field(page, "Complemento (Opcional)").fill("Sala 12");
    const created = page.waitForResponse(
      (res) => res.request().method() === "POST" && /\/patients$/.test(new URL(res.url()).pathname)
    );
    await page.getByRole("button", { name: "Salvar paciente" }).click();
    const response = await created;
    expect(response.status()).toBe(201);
    const body = await response.json();
    api.track(body.data.id);
    expect(response.request().postDataJSON().address).toEqual(buildAddress());
    expect(body.data.address).toEqual(buildAddress());
    expect(calls).toHaveLength(1);
    await expect(toast(page, "Paciente cadastrado com sucesso!")).toBeVisible();

    await page.goto(`/pacientes/${body.data.id}`);
    await expect(detailValue(page, "Endereço")).toHaveText(ADDRESS_LINE);
  });

  test("CEP não encontrado (404): erro no campo, mas o endereço digitado pode ser salvo", async ({ page, api }) => {
    const patient = await api.createPatient();
    const calls = await mockCep(page, 404, CEP_NOT_FOUND_BODY);
    await gotoEdit(page, patient);

    const cep = field(page, "CEP");
    await cep.pressSequentially("99999999");
    await expect(addressError(page, "cep")).toHaveText("CEP não encontrado");
    await expect(cep).toHaveAccessibleDescription("CEP não encontrado");
    await expect(politeStatus(page, "CEP não encontrado")).toHaveCount(1);
    await expect(field(page, "Rua")).toHaveValue("");
    expect(calls).toEqual(["99999999"]);

    await field(page, "Rua").fill("Rua Nova");
    await field(page, "Número").fill("10");
    await field(page, "Bairro").fill("Centro");
    await field(page, "Cidade").fill("Campinas");
    await uf(page).selectOption("SP");
    const sent = await saveEdit(page, patient.id);
    expect(sent.address).toMatchObject({ cep: "99999999", street: "Rua Nova", complement: null, state: "SP" });
    expect(await addressOf(api, patient.id)).toEqual({
      cep: "99999999",
      street: "Rua Nova",
      number: "10",
      complement: null,
      neighborhood: "Centro",
      city: "Campinas",
      state: "SP",
    });
  });

  test("serviço de CEP indisponível (503): aviso e preenchimento manual", async ({ page, api }) => {
    const patient = await api.createPatient();
    const calls = await mockCep(page, 503, CEP_UNAVAILABLE_BODY);
    await gotoEdit(page, patient);

    const cep = field(page, "CEP");
    await cep.pressSequentially("01310100");
    const note = page.locator("#pf-address-cep-note");
    await expect(note).toHaveText("Serviço de CEP indisponível. Preencha o endereço manualmente.");
    await expect(cep).toHaveAccessibleDescription("Serviço de CEP indisponível. Preencha o endereço manualmente.");
    // A note, not an error: the field is not marked invalid and nothing was filled in.
    await expect(addressError(page, "cep")).toHaveCount(0);
    await expect(cep).not.toHaveClass(/is-invalid/);
    await expect(field(page, "Rua")).toHaveValue("");
    expect(calls.length).toBeGreaterThanOrEqual(1);

    await field(page, "Rua").fill("Avenida Paulista");
    await field(page, "Número").fill("1578");
    await field(page, "Complemento (Opcional)").fill("Sala 12");
    await field(page, "Bairro").fill("Bela Vista");
    await field(page, "Cidade").fill("São Paulo");
    await uf(page).selectOption("SP");
    const sent = await saveEdit(page, patient.id);
    expect(sent.address).toEqual(buildAddress());
    expect(await addressOf(api, patient.id)).toEqual(buildAddress());
  });

  test("endereço parcial: validação no cliente (só o complemento é opcional), CEP e envio bloqueado", async ({
    page,
    api,
  }) => {
    const patient = await api.createPatient();
    const calls = await mockCep(page, 200, CEP_FOUND_BODY);
    await gotoEdit(page, patient);
    const puts = collectPuts(page, patient.id);

    await field(page, "Rua").fill("Rua Sem Número");
    await page.getByRole("button", { name: "Atualizar paciente" }).click();
    await expect(toast(page, "Preencha os campos obrigatórios para salvar!")).toBeVisible();
    for (const key of ["cep", "number", "neighborhood", "city", "state"]) {
      await expect(addressError(page, key)).toHaveText("Campo obrigatório");
    }
    await expect(addressError(page, "street")).toHaveCount(0);
    await expect(addressError(page, "complement")).toHaveCount(0);
    await expect(page.getByText("Campo obrigatório", { exact: true })).toHaveCount(5);
    await expect(field(page, "CEP")).toBeFocused();
    await expect(field(page, "Número")).toHaveAccessibleDescription("Campo obrigatório");
    await expect(uf(page)).toHaveAccessibleDescription("Campo obrigatório");

    // Typing clears the field's error; an incomplete CEP is rejected without a lookup.
    await field(page, "CEP").pressSequentially("0131010");
    await expect(addressError(page, "cep")).toHaveCount(0);
    await field(page, "Número").fill("1");
    await field(page, "Bairro").fill("Centro");
    await field(page, "Cidade").fill("Campinas");
    await uf(page).selectOption("SP");
    await page.getByRole("button", { name: "Atualizar paciente" }).click();
    await expect(addressError(page, "cep")).toHaveText("CEP inválido");
    await expect(toast(page, "Verifique os campos destacados.")).toBeVisible();

    expect(puts).toHaveLength(0);
    expect(calls).toHaveLength(0);
    await expect(page).toHaveURL(new RegExp(`/pacientes/${patient.id}/editar$`));
  });

  test("erros 422 do servidor (address.*) aparecem nos campos do endereço", async ({ page, api }) => {
    const patient = await api.createPatient();
    await mockCep(page, 200, CEP_FOUND_BODY);
    // The client accepts the form; the PUT is rewritten on its way to send values it would reject.
    await page.route(`**/api/patients/${patient.id}`, async (route) => {
      if (route.request().method() !== "PUT") return route.fallback();
      const body = route.request().postDataJSON();
      body.address = { ...body.address, cep: "0131010", state: "XX" };
      await route.continue({ postData: JSON.stringify(body) });
    });
    await gotoEdit(page, patient);

    await field(page, "CEP").pressSequentially("01310100");
    await expect(field(page, "Número")).toBeFocused();
    await field(page, "Número").fill("1578");
    const put = page.waitForResponse(
      (res) => res.request().method() === "PUT" && new URL(res.url()).pathname.endsWith(`/patients/${patient.id}`)
    );
    await page.getByRole("button", { name: "Atualizar paciente" }).click();
    const response = await put;
    expect(response.status()).toBe(422);
    const { errors } = await response.json();
    expect(Object.keys(errors).sort()).toEqual(["address.cep", "address.state"]);
    expect(errors["address.state"][0]).toContain("sigla de um estado brasileiro");

    await expect(toast(page, "Verifique os campos destacados para salvar!")).toBeVisible();
    await expect(addressError(page, "cep")).toHaveText(errors["address.cep"][0]);
    await expect(addressError(page, "state")).toHaveText(errors["address.state"][0]);
    await expect(uf(page)).toHaveAccessibleDescription(errors["address.state"][0]);
    expect(await addressOf(api, patient.id)).toBeNull();
  });

  test("edição: Remover endereço salva address null, detalhes mostram — e o histórico registra Endereço: …", async ({
    page,
    api,
  }) => {
    const created = await api.createPatient();
    // Added through the API: an "Edição" entry with every address field ("— → value").
    const patient = await api.updatePatient(created, { address: buildAddress() });
    expect(patient.address).toEqual(buildAddress());

    await gotoEdit(page, patient);
    await expect(field(page, "CEP")).toHaveValue("01310-100");
    await expect(field(page, "Rua")).toHaveValue("Avenida Paulista");
    await expect(uf(page)).toHaveValue("SP");

    await page.getByRole("button", { name: "Remover endereço" }).click();
    for (const label of ["CEP", "Rua", "Número", "Complemento (Opcional)", "Bairro", "Cidade"]) {
      await expect(field(page, label)).toHaveValue("");
    }
    await expect(uf(page)).toHaveValue("");
    await expect(politeStatus(page, "Endereço removido. Salve para confirmar a remoção.")).toHaveCount(1);
    await expect(field(page, "CEP")).toBeFocused();
    await expect(page.getByRole("button", { name: "Remover endereço" })).toHaveCount(0);

    const sent = await saveEdit(page, patient.id);
    expect(sent.address).toBeNull();
    expect(await addressOf(api, patient.id)).toBeNull();

    await page.goto(`/pacientes/${patient.id}`);
    await expect(detailValue(page, "Endereço")).toHaveText("—");

    await page.getByRole("tab", { name: "Histórico" }).click();
    await waitForHistory(page);
    // Newest first: removal, addition, creation.
    await expect(historyEntries(page)).toHaveCount(3);
    const [removal, addition] = [historyEntries(page).nth(0), historyEntries(page).nth(1)];
    await expect(historyChange(addition, "Endereço: CEP")).toContainText("Endereço: CEP: de — para 01310-100");
    await expect(historyChange(addition, "Endereço: UF")).toContainText("de — para SP");
    await expect(historyChange(addition, "Endereço: Complemento")).toContainText("de — para Sala 12");
    await expect(historyChange(removal, "Endereço: CEP")).toContainText("Endereço: CEP: de 01310-100 para —");
    await expect(historyChange(removal, "Endereço: Rua")).toContainText("de Avenida Paulista para —");
    await expect(historyChange(removal, "Endereço: Cidade")).toContainText("de São Paulo para —");
  });
});
