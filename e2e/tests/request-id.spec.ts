import { expect, test } from "../support/fixtures";
import { BASE_URL } from "../support/env";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// API only (no browser): every response carries X-Request-Id for log correlation.
test.describe("X-Request-Id", () => {
  test("toda resposta traz um UUID gerado, diferente a cada requisição (200, 404, 422, health)", async ({ api }) => {
    const responses = [
      await api.raw("get", "patients", { params: { per_page: 1 } }),
      await api.raw("get", "patients", { params: { per_page: 1 } }),
      await api.raw("get", "patients/999999999"),
      await api.raw("post", "patients", { data: {} }),
      await api.raw("get", "health"),
    ];
    expect(responses.map((r) => r.status())).toEqual([200, 200, 404, 422, 200]);
    const ids = responses.map((r) => r.headers()["x-request-id"]);
    for (const id of ids) expect(id).toMatch(UUID);
    expect(new Set(ids).size).toBe(ids.length);
  });

  test("um id recebido válido é devolvido como veio", async ({ api }) => {
    for (const id of ["3f2b8c4e-1d2a-4b5c-9e8f-0a1b2c3d4e5f", "e2e-trace_01.retry-2", "a".repeat(64)]) {
      const res = await api.raw("get", "patients", { params: { per_page: 1 }, headers: { "X-Request-Id": id } });
      expect(res.headers()["x-request-id"]).toBe(id);
    }
  });

  test("um id recebido inválido é trocado por um UUID novo", async ({ api }) => {
    for (const id of ["a".repeat(65), "tem espaço", "x;DROP TABLE", "novação", "<script>"]) {
      const res = await api.raw("get", "patients", { params: { per_page: 1 }, headers: { "X-Request-Id": id } });
      const echoed = res.headers()["x-request-id"];
      expect(echoed, `for ${JSON.stringify(id)}`).toMatch(UUID);
      expect(echoed).not.toBe(id);
    }
  });

  test("o front pode ler o header (CORS expõe X-Request-Id)", async ({ api }) => {
    const res = await api.raw("get", "patients", { params: { per_page: 1 }, headers: { Origin: BASE_URL } });
    expect(res.headers()["access-control-allow-origin"]).toBe(BASE_URL);
    expect((res.headers()["access-control-expose-headers"] ?? "").toLowerCase()).toContain("x-request-id");
  });
});
