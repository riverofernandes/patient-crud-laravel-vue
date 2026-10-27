import { request, type APIRequestContext, type APIResponse } from "@playwright/test";
import { API_URL } from "./env";
import { buildPatient, type Address, type PatientPayload } from "./data";

export type ConsultationStatus = "scheduled" | "confirmed" | "completed" | "no_show" | "cancelled";

export interface EyeData {
  visual_acuity_sc?: string | null;
  visual_acuity_cc?: string | null;
  spherical?: number | null;
  cylindrical?: number | null;
  axis?: number | null;
  addition?: number | null;
  iop?: number | null;
}

export interface EyeExamPayload {
  od?: EyeData;
  oe?: EyeData;
  diagnosis?: string | null;
  observations?: string | null;
}

export interface ConsultationPayload {
  /** ISO-8601; without offset it is read as America/Sao_Paulo. */
  scheduled_at: string;
  duration_minutes?: number;
  professional_name: string;
  professional_crm?: string | null;
  reason?: string | null;
  notes?: string | null;
}

export interface Consultation {
  id: number;
  patient_id: number;
  patient?: { id: number; name: string };
  scheduled_at: string;
  duration_minutes: number;
  status: ConsultationStatus;
  professional_name: string;
  professional_crm: string | null;
  reason: string | null;
  notes: string | null;
  eye_exam: (EyeExamPayload & { updated_at: string }) | null;
  created_at: string;
  updated_at: string;
}

export interface Patient extends PatientPayload {
  id: number;
  age: number;
  deleted_at: string | null;
  address: Address | null;
  /** Absolute URL of GET /patients/{id}/photo with ?v=<version>, or null. */
  photo_url: string | null;
}

/** Exam attachment of a consultation (Sprint 5). */
export interface Attachment {
  id: number;
  consultation_id: number;
  original_name: string;
  mime_type: string;
  size: number;
  description: string | null;
  download_url: string;
  created_at: string;
}

/** A file to upload (multipart). */
export interface UploadFile {
  name: string;
  mimeType: string;
  buffer: Buffer;
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
    method: "get" | "post" | "put" | "patch" | "delete",
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

  /** Full replacement (PUT): every editable field of `patient`, with `changes` applied. */
  async updatePatient(patient: Patient, changes: Partial<PatientPayload>): Promise<Patient> {
    const payload: PatientPayload = {
      name: patient.name,
      cpf: patient.cpf,
      rg: patient.rg,
      birth_date: patient.birth_date,
      sex: patient.sex,
      phone: patient.phone,
      email: patient.email,
      description: patient.description,
      // PUT is a full replacement: leaving the address out would remove it.
      address: patient.address ?? null,
      ...changes,
    };
    const res = await this.send("put", `patients/${patient.id}`, { data: payload });
    if (res.status() !== 200) throw new Error(`PUT /patients/${patient.id} failed: ${res.status()} ${await res.text()}`);
    return (await res.json()).data as Patient;
  }

  /** GET /patients with arbitrary query params (raw response). */
  async list(params: Record<string, string | number>): Promise<APIResponse> {
    return this.send("get", "patients", { params });
  }

  /** GET /patients/{id}/audits (raw response, so callers can assert 200/404 and meta). */
  async audits(id: number, params: Record<string, string | number> = {}): Promise<APIResponse> {
    return this.send("get", `patients/${id}/audits`, { params });
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

  /* ---------- Consultations (Sprint 4) ----------
   * No cleanup of their own: soft-deleting the patient hides its consultations from the agenda and
   * makes the nested routes 404 (and stops them from blocking time slots).
   */

  async createConsultation(patientId: number, payload: ConsultationPayload): Promise<Consultation> {
    const res = await this.send("post", `patients/${patientId}/consultations`, { data: payload });
    if (res.status() !== 201) {
      throw new Error(`POST /patients/${patientId}/consultations failed: ${res.status()} ${await res.text()}`);
    }
    return (await res.json()).data as Consultation;
  }

  /** PATCH /consultations/{id}/status (one transition). */
  async setStatus(id: number, status: ConsultationStatus): Promise<Consultation> {
    const res = await this.send("patch", `consultations/${id}/status`, { data: { status } });
    if (res.status() !== 200) {
      throw new Error(`PATCH /consultations/${id}/status failed: ${res.status()} ${await res.text()}`);
    }
    return (await res.json()).data as Consultation;
  }

  async saveEyeExam(id: number, exam: EyeExamPayload): Promise<Consultation> {
    const res = await this.send("put", `consultations/${id}/eye-exam`, { data: exam });
    if (res.status() !== 200) {
      throw new Error(`PUT /consultations/${id}/eye-exam failed: ${res.status()} ${await res.text()}`);
    }
    return (await res.json()).data as Consultation;
  }

  /** GET /consultations/{id} (raw response). */
  async getConsultation(id: number): Promise<APIResponse> {
    return this.send("get", `consultations/${id}`);
  }

  /** GET /consultations (agenda) with arbitrary query params (raw response). */
  async agenda(params: Record<string, string>): Promise<APIResponse> {
    return this.send("get", "consultations", { params });
  }

  /** GET /patients/{id}/timeline (raw response). */
  async timeline(patientId: number, params: Record<string, string | number> = {}): Promise<APIResponse> {
    return this.send("get", `patients/${patientId}/timeline`, { params });
  }

  /* ---------- Photo and attachments (Sprint 5) ---------- */

  /** POST /patients/{id}/photo (raw response, so callers can assert 200/422). */
  async uploadPhotoRaw(patientId: number, file: UploadFile): Promise<APIResponse> {
    return this.send("post", `patients/${patientId}/photo`, { multipart: { photo: file } });
  }

  async uploadPhoto(patientId: number, file: UploadFile): Promise<Patient> {
    const res = await this.uploadPhotoRaw(patientId, file);
    if (res.status() !== 200) {
      throw new Error(`POST /patients/${patientId}/photo failed: ${res.status()} ${await res.text()}`);
    }
    return (await res.json()).data as Patient;
  }

  /** GET of a photo URL as returned in `photo_url` (raw response). */
  async getUrl(url: string): Promise<APIResponse> {
    return this.send("get", url, { headers: { Accept: "*/*" } });
  }

  /** POST /consultations/{id}/attachments (raw response, so callers can assert 201/422). */
  async uploadAttachmentRaw(consultationId: number, file: UploadFile, description?: string): Promise<APIResponse> {
    const multipart: Record<string, string | UploadFile> = { file };
    if (description) multipart.description = description;
    return this.send("post", `consultations/${consultationId}/attachments`, { multipart });
  }

  async uploadAttachment(consultationId: number, file: UploadFile, description?: string): Promise<Attachment> {
    const res = await this.uploadAttachmentRaw(consultationId, file, description);
    if (res.status() !== 201) {
      throw new Error(
        `POST /consultations/${consultationId}/attachments failed: ${res.status()} ${await res.text()}`
      );
    }
    return (await res.json()).data as Attachment;
  }

  async attachments(consultationId: number): Promise<Attachment[]> {
    const res = await this.send("get", `consultations/${consultationId}/attachments`);
    if (!res.ok()) throw new Error(`GET /consultations/${consultationId}/attachments failed: ${res.status()}`);
    return (await res.json()).data as Attachment[];
  }

  /** Arbitrary request (raw response), e.g. to check headers such as X-Request-Id. */
  async raw(
    method: "get" | "post" | "put" | "patch" | "delete",
    url: string,
    options: Parameters<APIRequestContext["fetch"]>[1] = {}
  ): Promise<APIResponse> {
    return this.send(method, url, options);
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

