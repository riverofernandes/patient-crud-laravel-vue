import { api } from "./api";

export type Sex = "M" | "F";

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
}

/** Editable fields sent on POST/PUT. */
export type PatientPayload = Omit<
  Patient,
  "id" | "created_at" | "updated_at" | "age" | "deleted_at"
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
    };
    if (params.search) query.search = params.search;
    if (params.sort) query.sort = params.sort;
    if (params.direction) query.direction = params.direction;
    if (params.trashed) query.trashed = params.trashed;
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
