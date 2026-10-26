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
}

/** Editable fields sent on POST/PUT. */
export type PatientPayload = Omit<Patient, "id" | "created_at" | "updated_at">;

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

export interface ListParams {
  search?: string;
  page?: number;
  per_page?: number;
}

const RESOURCE = "/patients";

export const patientService = {
  async list(params: ListParams = {}): Promise<Paginated<Patient>> {
    const query: ListParams = {
      page: params.page ?? 1,
      per_page: params.per_page ?? 10,
    };
    if (params.search) query.search = params.search;
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
};
