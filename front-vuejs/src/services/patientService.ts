import {
  api,
  multipartConfig,
  parseBlobError,
  type UploadOptions,
} from "./api";
import {
  exportFilename,
  filenameFromContentDisposition,
} from "@/utils/download";

export type Sex = "M" | "F";

/** Patient address (Sprint 5). `cep` has digits only, `state` is the UF. */
export interface Address {
  cep: string;
  street: string;
  number: string;
  complement: string | null;
  neighborhood: string;
  city: string;
  state: string;
}

export interface Patient {
  id: number;
  name: string;
  cpf: string;
  rg: string;
  birth_date: string; // YYYY-MM-DD
  sex: Sex;
  phone: string;
  email: string | null;
  description: string | null;
  created_at: string;
  updated_at: string;
  /** Full years (America/Sao_Paulo); optional until every API serves it. */
  age?: number | null;
  /** ISO-8601, only filled for patients in the trash. */
  deleted_at?: string | null;
  /** Optional until every API serves it (Sprint 5). */
  address?: Address | null;
  /** Read-only absolute URL with `?v=` for cache busting, or null. */
  photo_url?: string | null;
}

/** Editable fields sent on POST/PUT. */
export type PatientPayload = Omit<
  Patient,
  "id" | "created_at" | "updated_at" | "age" | "deleted_at" | "photo_url"
>;

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface Paginated<T> {
  data: T[];
  meta: PaginationMeta;
}

export type SortField = "name" | "birth_date" | "created_at";
export type SortDirection = "asc" | "desc";

export interface ListParams {
  search?: string;
  page?: number;
  per_page?: number;
  sort?: SortField;
  direction?: SortDirection;
  /** "only" lists the trash (soft-deleted patients). */
  trashed?: "only";
  sex?: Sex;
  /** Full years, 0–150. */
  age_min?: number;
  age_max?: number;
}

/** Same filters/sorting as the listing, without pagination. */
export type ExportParams = Omit<ListParams, "page" | "per_page">;

export interface CsvExport {
  blob: Blob;
  filename: string;
}

export type AuditEvent =
  | "created"
  | "updated"
  | "deleted"
  | "restored"
  | "photo";

export interface AuditChange {
  old: string | null;
  new: string | null;
}

export interface Audit {
  id: number;
  event: AuditEvent;
  /** Raw API values (as in Patient); empty for deleted/restored. */
  changes: Record<string, AuditChange> | [];
  ip?: string | null;
  user_agent?: string | null;
  user_id?: number | null;
  created_at: string;
}

/** Export can take longer than a page of the listing. */
const EXPORT_TIMEOUT = 60000;

/** Copies the optional filter/sort params, skipping unset ones (0 is a valid age). */
function filterParams(params: ExportParams): ExportParams {
  const query: ExportParams = {};
  if (params.search) query.search = params.search;
  if (params.sort) query.sort = params.sort;
  if (params.direction) query.direction = params.direction;
  if (params.trashed) query.trashed = params.trashed;
  if (params.sex) query.sex = params.sex;
  if (params.age_min !== undefined) query.age_min = params.age_min;
  if (params.age_max !== undefined) query.age_max = params.age_max;
  return query;
}

export interface CpfAvailability {
  available: boolean;
  message?: string;
}

const RESOURCE = "/patients";

export const patientService = {
  async list(params: ListParams = {}): Promise<Paginated<Patient>> {
    const query: ListParams = {
      page: params.page ?? 1,
      per_page: params.per_page ?? 10,
      ...filterParams(params),
    };
    const { data } = await api.get<Paginated<Patient>>(RESOURCE, {
      params: query,
    });
    return data;
  },

  async get(id: number | string): Promise<Patient> {
    const { data } = await api.get<{ data: Patient }>(`${RESOURCE}/${id}`);
    return data.data;
  },

  async create(payload: PatientPayload): Promise<Patient> {
    const { data } = await api.post<{ data: Patient }>(RESOURCE, payload);
    return data.data;
  },

  async update(id: number | string, payload: PatientPayload): Promise<Patient> {
    const { data } = await api.put<{ data: Patient }>(
      `${RESOURCE}/${id}`,
      payload
    );
    return data.data;
  },

  async remove(id: number | string): Promise<void> {
    await api.delete(`${RESOURCE}/${id}`);
  },

  async restore(id: number | string): Promise<Patient> {
    const { data } = await api.post<{ data: Patient }>(
      `${RESOURCE}/${id}/restore`
    );
    return data.data;
  },

  /**
   * Downloads the CSV export as a Blob. The filename comes from
   * Content-Disposition when the browser can read it (cross-origin it needs
   * Access-Control-Expose-Headers), otherwise it is built locally.
   */
  async exportCsv(params: ExportParams = {}): Promise<CsvExport> {
    const response = await api
      .get<Blob>(`${RESOURCE}/export`, {
        params: filterParams(params),
        responseType: "blob",
        headers: { Accept: "text/csv, application/json" },
        timeout: EXPORT_TIMEOUT,
      })
      .catch(parseBlobError);
    const header = response.headers?.["content-disposition"];
    return {
      blob: response.data,
      filename:
        filenameFromContentDisposition(
          typeof header === "string" ? header : undefined
        ) || exportFilename(),
    };
  },

  /** Change history, newest first. */
  async audits(
    id: number | string,
    params: { page?: number; per_page?: number } = {}
  ): Promise<Paginated<Audit>> {
    const { data } = await api.get<Paginated<Audit>>(
      `${RESOURCE}/${id}/audits`,
      { params: { page: params.page ?? 1, per_page: params.per_page ?? 10 } }
    );
    return data;
  },

  /** Sends a new photo (JPEG/PNG/WebP, max. 2 MB); returns the updated patient. */
  async uploadPhoto(
    id: number | string,
    file: Blob,
    options: UploadOptions = {}
  ): Promise<Patient> {
    const body = new FormData();
    body.append("photo", file);
    const { data } = await api.post<{ data: Patient }>(
      `${RESOURCE}/${id}/photo`,
      body,
      multipartConfig(options)
    );
    return data.data;
  },

  async removePhoto(id: number | string): Promise<void> {
    await api.delete(`${RESOURCE}/${id}/photo`);
  },

  /** Whether a CPF can be used (`ignore` = the patient being edited). */
  async checkCpf(
    cpf: string,
    options: { ignore?: number | string; signal?: AbortSignal } = {}
  ): Promise<CpfAvailability> {
    const params: Record<string, string> = { cpf };
    if (options.ignore !== undefined) params.ignore = String(options.ignore);
    const { data } = await api.get<CpfAvailability>(
      `${RESOURCE}/cpf-availability`,
      { params, signal: options.signal }
    );
    return data;
  },
};
