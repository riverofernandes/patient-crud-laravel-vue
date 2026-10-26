import { request, type APIRequestContext, type APIResponse } from "@playwright/test";
import { API_URL } from "./env";
import { buildPatient, type PatientPayload } from "./data";

export interface Patient extends PatientPayload {
  id: number;
  age: number;
  deleted_at: string | null;
}

const MAX_ATTEMPTS = 6;

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Thin API client used to arrange and clean up test data.
 * Every patient created through it (or registered with `track`) is deleted in `dispose`.
 */
export class PatientApi {
  private readonly created = new Set<number>();
  /** Deleted through this client (and not restored through it since): nothing left to clean up. */
  private readonly trashed = new Set<number>();

  private constructor(private readonly ctx: APIRequestContext) {}

  static async create(): Promise<PatientApi> {
    const ctx = await request.newContext({
      // Trailing slash so relative paths resolve under /api.
      baseURL: `${API_URL}/`,
      extraHTTPHeaders: { Accept: "application/json" },
    });
    return new PatientApi(ctx);
  }

  /** Retries on 429 (the API rate limit is shared with the browser). */
  private async send(
    method: "get" | "post" | "put" | "delete",
    url: string,
    options: Parameters<APIRequestContext["fetch"]>[1] = {}
  ): Promise<APIResponse> {
    for (let attempt = 1; ; attempt++) {
      const res = await this.ctx.fetch(url, { ...options, method });
      if (res.status() !== 429 || attempt >= MAX_ATTEMPTS) return res;
      const retryAfter = Number(res.headers()["retry-after"] ?? "1");
      await delay(Math.min(Math.max(retryAfter, 1), 30) * 1000);
    }
  }

  async createPatient(overrides: Partial<PatientPayload> = {}): Promise<Patient> {
    for (let attempt = 1; ; attempt++) {
      const payload = buildPatient(overrides);
      const res = await this.send("post", "patients", { data: payload });
      if (res.status() === 201) {
        const patient = (await res.json()).data as Patient;
        this.created.add(patient.id);
        return patient;
      }
      const body = await res.text();
      // Random CPF collided with an existing one: try another (unless the caller fixed the CPF).
      if (res.status() === 422 && !overrides.cpf && body.includes("cpf") && attempt < 5) {
        continue;
      }
      throw new Error(`POST /patients failed: ${res.status()} ${body}`);
    }
  }

  /** Creates `count` patients sharing the same name prefix. */
  async createMany(count: number, namePrefix: string): Promise<Patient[]> {
    const patients: Patient[] = [];
    for (let i = 0; i < count; i++) {
      // Zero-padded letters keep alphabetical order == creation order.
      const suffix = String.fromCharCode(97 + Math.floor(i / 26)) + String.fromCharCode(97 + (i % 26));
      patients.push(await this.createPatient({ name: `${namePrefix} ${suffix}` }));
    }
    return patients;
  }

  async getPatient(id: number): Promise<APIResponse> {
    return this.send("get", `patients/${id}`);
  }

  async search(term: string): Promise<Patient[]> {
    const res = await this.send("get", "patients", { params: { search: term, per_page: 100 } });
    if (!res.ok()) throw new Error(`GET /patients failed: ${res.status()}`);
    return (await res.json()).data as Patient[];
  }

  /** Patients in the trash (soft-deleted) matching `term`. */
  async listTrashed(term: string): Promise<Patient[]> {
    const res = await this.send("get", "patients", {
      params: { search: term, trashed: "only", per_page: 100 },
    });
    if (!res.ok()) throw new Error(`GET /patients?trashed=only failed: ${res.status()}`);
    return (await res.json()).data as Patient[];
  }

  /** Restores a trashed patient. Returns the raw response so callers can assert 200/404/409. */
  async restorePatient(id: number): Promise<APIResponse> {
    const res = await this.send("post", `patients/${id}/restore`);
    if (res.status() === 200) this.trashed.delete(id);
    return res;
  }

  /** Registers a patient created through the UI so it gets cleaned up. */
  track(id: number): void {
    this.created.add(id);
    // Tracking again (e.g. after restoring through the UI) means "clean this one up".
    this.trashed.delete(id);
  }

  /** Looks a patient up by its exact (unique) name and tracks it for cleanup. */
  async trackByName(name: string): Promise<Patient | undefined> {
    const found = (await this.search(name)).find((p) => p.name === name);
    if (found) this.track(found.id);
    return found;
  }

  async deletePatient(id: number): Promise<void> {
    const res = await this.send("delete", `patients/${id}`);
    if (![204, 404].includes(res.status())) {
      throw new Error(`DELETE /patients/${id} failed: ${res.status()}`);
    }
    this.trashed.add(id);
  }

  /**
   * Deletes (soft delete) every tracked patient. Patients deleted through the UI, or restored
   * through the UI or `restorePatient`, are deleted here (404 if already in the trash is fine).
   * Patients deleted with `deletePatient` are skipped to save requests (rate limit): a test that
   * then restores one through the UI must call `track(id)` again.
   * There is no hard-delete endpoint, so cleaned-up patients stay in the trash.
   */
  async dispose(): Promise<void> {
    const errors: unknown[] = [];
    for (const id of this.created) {
      if (this.trashed.has(id)) continue;
      try {
        await this.deletePatient(id);
      } catch (e) {
        errors.push(e);
      }
    }
    this.created.clear();
    this.trashed.clear();
    await this.ctx.dispose();
    if (errors.length) throw errors[0];
  }
}

