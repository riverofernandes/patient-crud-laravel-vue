import { readFile } from "node:fs/promises";
import type { Download, Page, Response } from "@playwright/test";
import {
  expect,
  formatDateTimeSaoPaulo,
  gotoListingQuery,
  gotoTrash,
  test,
  toast,
  trashRows,
  waitForTable,
} from "../support/fixtures";
import type { Patient } from "../support/api";
import { buildAddress, isoToBr, maskCpf, maskPhone, uniqueTag } from "../support/data";

const HEADER = "Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descrição;CEP;Endereço;Cidade;UF";
const FILENAME = /^pacientes-(\d{8})-(\d{4})\.csv$/;

interface Csv {
  raw: Buffer;
  text: string; // without the BOM
  lines: string[];
  rows: string[][];
}

/** Minimal RFC 4180 parser for ";"-separated content (quoted cells may contain ; " and line breaks). */
function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = "";
  let quoted = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (quoted) {
      if (ch === '"' && text[i + 1] === '"') {
        cell += '"';
        i++;
      } else if (ch === '"') quoted = false;
      else cell += ch;
    } else if (ch === '"') quoted = true;
    else if (ch === ";") {
      row.push(cell);
      cell = "";
    } else if (ch === "\r" && text[i + 1] === "\n") {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = "";
      i++;
    } else cell += ch;
  }
  if (cell !== "" || row.length) rows.push([...row, cell]);
  return rows;
}

function readCsv(raw: Buffer): Csv {
  const text = raw.subarray(3).toString("utf8");
  return { raw, text, lines: text.split("\r\n"), rows: parseCsv(text) };
}

/** "pacientes-AAAAMMDD-HHMM" for the given instant in America/Sao_Paulo. */
function stampSaoPaulo(date: Date): string {
  const p = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      timeZone: "America/Sao_Paulo",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
    })
      .formatToParts(date)
      .map((x) => [x.type, x.value])
  );
  return `${p.year}${p.month}${p.day}-${p.hour}${p.minute}`;
}

/** Clicks "Exportar CSV" and returns the download plus the API response behind it. */
async function exportFromPage(page: Page): Promise<{ download: Download; response: Response; csv: Csv }> {
  const before = Date.now();
  const responsePromise = page.waitForResponse(
    (res) => res.request().method() === "GET" && new URL(res.url()).pathname.endsWith("/patients/export")
  );
  const downloadPromise = page.waitForEvent("download");
  await page.getByRole("button", { name: "Exportar CSV" }).click();
  const [response, download] = await Promise.all([responsePromise, downloadPromise]);
  const after = Date.now();

  // Name from Content-Disposition, stamped in São Paulo time (allow the minute to roll over).
  const filename = download.suggestedFilename();
  expect(filename).toMatch(FILENAME);
  const stamps = new Set([stampSaoPaulo(new Date(before - 60_000)), stampSaoPaulo(new Date(before)), stampSaoPaulo(new Date(after))]);
  expect([...stamps]).toContain(filename.replace(/^pacientes-|\.csv$/g, ""));
  await expect(toast(page, `Exportação concluída: ${filename}`)).toBeVisible();

  const path = await download.path();
  const csv = readCsv(await readFile(path));
  return { download, response, csv };
}

/** Byte/format checks shared by every export. */
function expectCsvFormat(csv: Csv, header: string): void {
  expect([...csv.raw.subarray(0, 3)], "UTF-8 BOM").toEqual([0xef, 0xbb, 0xbf]);
  expect(csv.lines[0]).toBe(header);
  expect(csv.text.endsWith("\r\n"), "last line ends with CRLF").toBe(true);
  expect(/(^|[^\r])\n/.test(csv.text), "no bare LF line breaks").toBe(false);
  for (const row of csv.rows) expect(row).toHaveLength(header.split(";").length);
}

function rowOf(csv: Csv, name: string): string[] | undefined {
  return csv.rows.find((r) => r[0] === name || r[0] === `'${name}`);
}

/** Address columns: CEP 00000-000, "rua, número - complemento - bairro", city, UF (all empty without address). */
function addressCells(p: Patient): string[] {
  const a = p.address;
  if (!a) return ["", "", "", ""];
  const line = [`${a.street}, ${a.number}`, a.complement, a.neighborhood].filter(Boolean).join(" - ");
  return [a.cep.replace(/^(\d{5})(\d{3})$/, "$1-$2"), line, a.city, a.state];
}

function expectedRow(p: Patient): string[] {
  return [
    p.name,
    maskCpf(p.cpf),
    p.rg,
    isoToBr(p.birth_date),
    String(p.age),
    p.sex === "F" ? "Feminino" : "Masculino",
    maskPhone(p.phone),
    p.email ?? "",
    p.description ?? "",
    ...addressCells(p),
  ];
}

test.describe("Exportar CSV", () => {
  test("listagem: baixa o arquivo com os filtros aplicados, formato pt-BR e proteção contra fórmulas", async ({
    page,
    api,
  }) => {
    const prefix = uniqueTag();
    const female = await api.createPatient({
      name: `${prefix} aa`,
      sex: "F",
      birth_date: "1990-01-01",
      email: "e2e@example.com",
      // Separator and quote inside a value: the cell must be quoted.
      description: 'Miopia; retorno "anual"',
      address: buildAddress({ number: "S/N", complement: "Bloco B; apto 3" }),
    });
    expect(female.address?.cep).toBe("01310100");
    const male = await api.createPatient({ name: `${prefix} ab`, sex: "M", birth_date: "1985-12-31" });
    // Spreadsheet formula injection: both cells must get a leading apostrophe.
    const formula = await api.createPatient({
      name: `=${prefix} formula`,
      sex: "F",
      description: "@SUM(1+1)",
    });

    await gotoListingQuery(page, { search: prefix, sex: "F" });
    await expect(page.getByRole("button", { name: "Exportar CSV" })).toBeEnabled();
    const { response, csv } = await exportFromPage(page);

    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toBe("text/csv; charset=UTF-8");
    // Without this CORS header the SPA can't read the filename (it would fall back to a local one).
    expect((response.headers()["access-control-expose-headers"] ?? "").toLowerCase()).toContain(
      "content-disposition"
    );
    // The export request carries the same filters as the table.
    const query = new URL(response.url()).searchParams;
    expect(query.get("search")).toBe(prefix);
    expect(query.get("sex")).toBe("F");

    expectCsvFormat(csv, HEADER);
    expect(csv.text).toContain('"Miopia; retorno ""anual"""');
    // Only the matching rows (header + 2 female patients)
    expect(csv.rows).toHaveLength(3);
    expect(rowOf(csv, male.name), "male patient filtered out").toBeUndefined();
    expect(rowOf(csv, female.name)).toEqual(expectedRow(female));
    expect(rowOf(csv, formula.name)).toEqual([
      `'${formula.name}`,
      ...expectedRow(formula).slice(1, 8),
      "'@SUM(1+1)",
      "",
      "",
      "",
      "",
    ]);
    // Address columns of the patient with an address (the ";" in the complement forces quoting).
    expect(rowOf(csv, female.name)!.slice(9)).toEqual([
      "01310-100",
      "Avenida Paulista, S/N - Bloco B; apto 3 - Bela Vista",
      "São Paulo",
      "SP",
    ]);
    expect(csv.text).toContain('"Avenida Paulista, S/N - Bloco B; apto 3 - Bela Vista"');

    // Masculino label (same endpoint, other filter, straight from the API)
    const res = await api.list({ search: prefix, sex: "M", per_page: 1 }); // sanity: listing agrees
    expect((await res.json()).meta.total).toBe(1);
    const exportM = readCsv(
      Buffer.from(await (await page.request.get(response.url().replace("sex=F", "sex=M"))).body())
    );
    expectCsvFormat(exportM, HEADER);
    expect(exportM.rows.slice(1)).toEqual([expectedRow(male)]);
  });

  test("lixeira: inclui a coluna Excluído em", async ({ page, api }) => {
    const prefix = uniqueTag();
    const patient = await api.createPatient({ name: `${prefix} aa`, sex: "M" });
    await api.deletePatient(patient.id);
    const [trashed] = await api.listTrashed(prefix);
    expect(trashed.id).toBe(patient.id);

    await gotoTrash(page, prefix);
    await expect(trashRows(page)).toHaveCount(1);
    const { response, csv } = await exportFromPage(page);

    expect(new URL(response.url()).searchParams.get("trashed")).toBe("only");
    expectCsvFormat(csv, `${HEADER};Excluído em`);
    expect(csv.rows.slice(1)).toEqual([[...expectedRow(patient), formatDateTimeSaoPaulo(trashed.deleted_at!)]]);
  });

  test("erro na exportação mostra toast e o botão volta ao normal", async ({ page }) => {
    // A 4xx without a message: the only case that shows the export's own fallback text (5xx, 429 and
    // network errors show the generic messages of services/api.ts).
    await page.route(/\/api\/patients\/export/, (route) =>
      route.fulfill({ status: 400, body: "", headers: { "access-control-allow-origin": "*" } })
    );
    await gotoListingQuery(page, {});
    await waitForTable(page);
    const button = page.getByRole("button", { name: "Exportar CSV" });
    await button.click();
    await expect(toast(page, "Não foi possível exportar os pacientes.")).toBeVisible();
    await expect(button).toBeEnabled();
    await expect(button).not.toHaveAttribute("aria-busy", "true");
  });
});
