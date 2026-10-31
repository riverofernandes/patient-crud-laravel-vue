/**
 * API-level regression tests for the audit fixes (fix/audit-findings). Each test says why it would fail on the
 * behavior before the fix. They go through the API helper only (no browser), so they are cheap.
 */
import { addDaysIso, expect, randomFutureMonday, test } from "../support/fixtures";
import { buildAddress, professionalName, tokenOf } from "../support/data";
import { photoPng } from "../support/files";
import type { PatientApi } from "../support/api";

const RESTORE_NOTE =
  "Cancelado automaticamente ao restaurar o paciente: o horário foi ocupado por outro atendimento.";
const JSON_HEADERS = { "Content-Type": "application/json" };

async function auditCount(api: PatientApi, id: number): Promise<number> {
  const res = await api.audits(id, { per_page: 1 });
  expect(res.status()).toBe(200);
  return (await res.json()).meta.total as number;
}

test.describe("Auditoria: regressões da API", () => {
  test("#1 restaurar cancela o atendimento futuro do paciente que conflita com outro, com anotação e meta", async ({
    api,
  }) => {
    // Before the fix the restore just un-trashed the patient: A's consultation became active again on top of B's
    // (double booking), the response had no meta.cancelled_consultations and the agenda showed both as active.
    const professional = professionalName("Dra.");
    const day = addDaysIso(randomFutureMonday(), 2);
    const slot = `${day}T10:00:00-03:00`;
    const a = await api.createPatient();
    const b = await api.createPatient();
    const aConsultation = await api.createConsultation(a.id, {
      scheduled_at: slot,
      professional_name: professional,
      notes: "Trazer exames anteriores.",
    });
    await api.deletePatient(a.id);
    // A's slot is free while A is in the trash.
    const bConsultation = await api.createConsultation(b.id, {
      scheduled_at: `${day}T10:15:00-03:00`,
      professional_name: professional,
    });

    const res = await api.restorePatient(a.id);
    api.track(a.id);
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body.data.id).toBe(a.id);
    expect(body.data.deleted_at).toBeNull();
    expect(body.meta.cancelled_consultations).toEqual([
      { id: aConsultation.id, scheduled_at: aConsultation.scheduled_at, professional_name: professional },
    ]);

    const cancelled = (await (await api.getConsultation(aConsultation.id)).json()).data;
    expect(cancelled.status).toBe("cancelled");
    // Appended after the existing notes.
    expect(cancelled.notes).toBe(`Trazer exames anteriores.\n\n${RESTORE_NOTE}`);
    expect((await (await api.getConsultation(bConsultation.id)).json()).data.status).toBe("scheduled");

    const agenda = await api.agenda({ date_from: day, date_to: day, professional: tokenOf(professional) });
    expect(agenda.status()).toBe(200);
    const active = ((await agenda.json()).data as { id: number; status: string }[]).filter((c) =>
      ["scheduled", "confirmed"].includes(c.status)
    );
    expect(active.map((c) => c.id)).toEqual([bConsultation.id]);
  });

  test("#1 sem conflito o meta vem vazio; sobreposição passada fica, e editar só as anotações dela dá 200", async ({
    api,
  }) => {
    // Restore only cancels FUTURE consultations, so a past overlap survives it: that is how an "existing overlap"
    // is built here. Before the fix, PUT re-ran the slot-conflict check even when the slot did not change, so
    // editing only the notes of a consultation that already overlapped another one was a 422.
    const professional = professionalName();
    const pastSlot = "2025-02-11T14:00:00-03:00";
    const a = await api.createPatient();
    const b = await api.createPatient();
    const aPast = await api.createConsultation(a.id, { scheduled_at: pastSlot, professional_name: professional });
    await api.deletePatient(a.id);
    const bPast = await api.createConsultation(b.id, { scheduled_at: pastSlot, professional_name: professional });

    const res = await api.restorePatient(a.id);
    api.track(a.id);
    expect(res.status()).toBe(200);
    expect((await res.json()).meta).toEqual({ cancelled_consultations: [] });
    expect((await (await api.getConsultation(aPast.id)).json()).data.status).toBe("scheduled");

    const edit = await api.raw("put", `consultations/${aPast.id}`, {
      data: {
        scheduled_at: aPast.scheduled_at,
        duration_minutes: aPast.duration_minutes,
        professional_name: professional,
        notes: "Só as anotações mudaram.",
      },
    });
    expect(edit.status(), await edit.text()).toBe(200);
    expect((await edit.json()).data.notes).toBe("Só as anotações mudaram.");

    // Moving it (a real slot change) is still checked against B.
    const move = await api.raw("put", `consultations/${aPast.id}`, {
      data: { scheduled_at: "2025-02-11T14:10:00-03:00", professional_name: professional },
    });
    expect(move.status()).toBe(422);
    expect((await (await api.getConsultation(bPast.id)).json()).data.status).toBe("scheduled");
  });

  test("#3 JSON malformado em PATCH/PUT dá 400 'JSON inválido.'; PATCH com corpo vazio é no-op 200", async ({
    api,
  }) => {
    // Before the fix Laravel treated an unparseable body as empty: PUT answered 422 ("campo obrigatório") and PATCH
    // a silent 200 no-op, so a client typo looked like success.
    const patient = await api.createPatient();
    const before = await auditCount(api, patient.id);

    for (const method of ["patch", "put"] as const) {
      const res = await api.raw(method, `patients/${patient.id}`, {
        headers: JSON_HEADERS,
        // A Buffer is sent as is (a string with a JSON Content-Type would be JSON-encoded by Playwright).
        data: Buffer.from('{"name": "E2E Quebrado",'),
      });
      expect(res.status(), method).toBe(400);
      expect(await res.json()).toEqual({ message: "JSON inválido." });
      expect(res.headers()["x-request-id"]).toBeTruthy();
    }

    const empty = await api.raw("patch", `patients/${patient.id}`, { headers: JSON_HEADERS, data: "" });
    expect(empty.status()).toBe(200);
    expect((await empty.json()).data.name).toBe(patient.name);
    const emptyObject = await api.raw("patch", `patients/${patient.id}`, { data: {} });
    expect(emptyObject.status()).toBe(200);

    const after = (await (await api.getPatient(patient.id)).json()).data;
    expect(after.name).toBe(patient.name);
    expect(await auditCount(api, patient.id)).toBe(before);
  });

  test("PATCH só com o nome altera só o nome", async ({ api }) => {
    // Before PR #29 PATCH was the same full replacement as PUT: omitted optional fields (email, description,
    // address) were wiped and omitted required ones were a 422.
    const patient = await api.createPatient({
      email: "e2e.patch@example.com",
      description: "Miopia leve.",
      address: buildAddress(),
    });
    const res = await api.raw("patch", `patients/${patient.id}`, { data: { name: `${patient.name} Lima` } });
    expect(res.status(), await res.text()).toBe(200);
    const updated = (await res.json()).data;
    const { name: _n, updated_at: _u1, ...rest } = updated;
    const { name: _o, updated_at: _u2, ...original } = patient as unknown as Record<string, unknown>;
    expect(updated.name).toBe(`${patient.name} Lima`);
    expect(rest).toEqual(original);
  });

  test("#4 PATCH address \"\" é 422 e mantém o endereço; address null remove", async ({ api }) => {
    // Before the fix ConvertEmptyStringsToNull turned "" into null, so {"address": ""} silently REMOVED the address
    // with a 200.
    const patient = await api.createPatient({ address: buildAddress() });

    const blank = await api.raw("patch", `patients/${patient.id}`, { data: { address: "" } });
    expect(blank.status()).toBe(422);
    expect((await blank.json()).errors.address).toEqual(["O campo endereço deve ser um objeto."]);
    expect((await (await api.getPatient(patient.id)).json()).data.address).toEqual(buildAddress());

    const text = await api.raw("patch", `patients/${patient.id}`, { data: { address: "Rua X, 10" } });
    expect(text.status()).toBe(422);

    const removed = await api.raw("patch", `patients/${patient.id}`, { data: { address: null } });
    expect(removed.status()).toBe(200);
    expect((await removed.json()).data.address).toBeNull();
    expect((await (await api.getPatient(patient.id)).json()).data.address).toBeNull();
  });

  test("#15 paciente na lixeira tem photo_url null; restaurado volta a ter URL que carrega", async ({ api }) => {
    // Before the fix the trash listing returned a photo_url whose route is a 404 for trashed patients (broken image).
    const patient = await api.createPatient();
    const withPhoto = await api.uploadPhoto(patient.id, photoPng());
    expect(withPhoto.photo_url).not.toBeNull();
    await api.deletePatient(patient.id);

    const trashed = (await api.listTrashed(patient.name)).find((p) => p.id === patient.id);
    expect(trashed).toBeDefined();
    expect(trashed!.photo_url).toBeNull();
    expect((await api.getUrl(withPhoto.photo_url!)).status()).toBe(404);

    const restored = await api.restorePatient(patient.id);
    api.track(patient.id);
    expect(restored.status()).toBe(200);
    const url = (await restored.json()).data.photo_url as string | null;
    expect(url).not.toBeNull();
    expect((await api.getUrl(url!)).status()).toBe(200);
  });

  test("#16 405 em JSON pt-BR com Allow; id não numérico dá a mensagem do recurso", async ({ api }) => {
    // Before the fix a routing 405 was Laravel's English message (HTML without Accept: application/json) and
    // /patients/abc matched no route, answering the generic "Recurso não encontrado.".
    for (const accept of ["application/json", "text/html"]) {
      const res = await api.raw("delete", "patients", { headers: { Accept: accept } });
      expect(res.status(), accept).toBe(405);
      expect(res.headers()["content-type"]).toContain("application/json");
      expect(await res.json()).toEqual({ message: "Método não permitido." });
      const allow = res.headers()["allow"] ?? "";
      expect(allow).toContain("GET");
      expect(allow).toContain("POST");
    }

    const cases: [string, string][] = [
      ["patients/abc", "Paciente não encontrado."],
      ["patients/0", "Paciente não encontrado."],
      ["consultations/abc", "Atendimento não encontrado."],
      ["patients/1/nope", "Recurso não encontrado."],
    ];
    for (const [path, message] of cases) {
      const res = await api.raw("get", path);
      expect(res.status(), path).toBe(404);
      expect(await res.json(), path).toEqual({ message });
    }
  });
});
