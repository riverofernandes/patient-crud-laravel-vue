import { api, parseBlobError } from "./api";
import type { Audit, Paginated } from "./patientService";
import { filenameFromContentDisposition } from "@/utils/download";

export type ConsultationStatus =
  | "scheduled"
  | "confirmed"
  | "completed"
  | "no_show"
  | "cancelled";

/** Refraction / exam values of one eye (all optional). */
export interface EyeData {
  /** Snellen "20/N". */
  visual_acuity_sc: string | null;
  visual_acuity_cc: string | null;
  spherical: number | null;
  cylindrical: number | null;
  axis: number | null;
  addition: number | null;
  /** Intraocular pressure, mmHg. */
  iop: number | null;
}

export type Eye = "od" | "oe";

export interface EyeExam {
  od: EyeData;
  oe: EyeData;
  diagnosis: string | null;
  observations: string | null;
  updated_at?: string | null;
}

export type EyeExamPayload = Omit<EyeExam, "updated_at">;

export interface Consultation {
  id: number;
  patient_id: number;
  /** Present on the agenda and on the detail. */
  patient?: { id: number; name: string } | null;
  /** ISO-8601 with the São Paulo offset. */
  scheduled_at: string;
  duration_minutes: number;
  status: ConsultationStatus;
  professional_name: string;
  professional_crm: string | null;
  reason: string | null;
  notes: string | null;
  eye_exam: EyeExam | null;
  created_at: string;
  updated_at: string;
}

/** Editable fields (POST / PUT). `scheduled_at` may omit the offset (São Paulo). */
export interface ConsultationPayload {
  scheduled_at: string;
  duration_minutes: number;
  professional_name: string;
  professional_crm: string | null;
  reason: string | null;
  notes: string | null;
}

export interface AgendaParams {
  /** YYYY-MM-DD, São Paulo days (max. 31-day range). */
  date_from: string;
  date_to: string;
  status?: ConsultationStatus[];
  professional?: string;
}

export type TimelineItem =
  | { type: "consultation"; occurred_at: string; consultation: Consultation }
  | { type: "audit"; occurred_at: string; audit: Audit };

export interface PageParams {
  page?: number;
  per_page?: number;
}

export interface PdfDownload {
  blob: Blob;
  filename: string;
}

const pageQuery = (params: PageParams) => ({
  page: params.page ?? 1,
  per_page: params.per_page ?? 10,
});

/** receita-<id>-AAAAMMDD.pdf, with the consultation's date (same as the API). */
export function prescriptionFilename(id: number | string, date = ""): string {
  const digits = date.replace(/\D/g, "").slice(0, 8);
  return digits.length === 8
    ? `receita-${id}-${digits}.pdf`
    : `receita-${id}.pdf`;
}

export const consultationService = {
  async listByPatient(
    patientId: number | string,
    params: PageParams = {}
  ): Promise<Paginated<Consultation>> {
    const { data } = await api.get<Paginated<Consultation>>(
      `/patients/${patientId}/consultations`,
      { params: pageQuery(params) }
    );
    return data;
  },

  async create(
    patientId: number | string,
    payload: ConsultationPayload
  ): Promise<Consultation> {
    const { data } = await api.post<{ data: Consultation }>(
      `/patients/${patientId}/consultations`,
      payload
    );
    return data.data;
  },

  /** Agenda: no pagination, `scheduled_at` ascending, with `patient`. */
  async agenda(params: AgendaParams): Promise<Consultation[]> {
    const query: Record<string, string> = {
      date_from: params.date_from,
      date_to: params.date_to,
    };
    if (params.status?.length) query.status = params.status.join(",");
    if (params.professional?.trim())
      query.professional = params.professional.trim();
    const { data } = await api.get<{ data: Consultation[] }>("/consultations", {
      params: query,
    });
    return data.data;
  },

  async get(id: number | string): Promise<Consultation> {
    const { data } = await api.get<{ data: Consultation }>(
      `/consultations/${id}`
    );
    return data.data;
  },

  async update(
    id: number | string,
    payload: ConsultationPayload
  ): Promise<Consultation> {
    const { data } = await api.put<{ data: Consultation }>(
      `/consultations/${id}`,
      payload
    );
    return data.data;
  },

  async changeStatus(
    id: number | string,
    status: ConsultationStatus
  ): Promise<Consultation> {
    const { data } = await api.patch<{ data: Consultation }>(
      `/consultations/${id}/status`,
      { status }
    );
    return data.data;
  },

  async saveEyeExam(
    id: number | string,
    payload: EyeExamPayload
  ): Promise<Consultation> {
    const { data } = await api.put<{ data: Consultation }>(
      `/consultations/${id}/eye-exam`,
      payload
    );
    return data.data;
  },

  /**
   * Glasses prescription PDF as a Blob. Errors (422 without refraction) are
   * JSON turned back from the Blob so getErrorMessage() can read them.
   */
  async downloadPrescription(
    id: number | string,
    scheduledDate = ""
  ): Promise<PdfDownload> {
    const response = await api
      .get<Blob>(`/consultations/${id}/prescription`, {
        responseType: "blob",
        headers: { Accept: "application/pdf, application/json" },
        timeout: 60000,
      })
      .catch(parseBlobError);
    const header = response.headers?.["content-disposition"];
    return {
      blob: response.data,
      filename:
        filenameFromContentDisposition(
          typeof header === "string" ? header : undefined
        ) || prescriptionFilename(id, scheduledDate),
    };
  },

  /** Consultations + change history, newest first. */
  async timeline(
    patientId: number | string,
    params: PageParams = {}
  ): Promise<Paginated<TimelineItem>> {
    const { data } = await api.get<Paginated<TimelineItem>>(
      `/patients/${patientId}/timeline`,
      { params: pageQuery(params) }
    );
    return data;
  },
};
