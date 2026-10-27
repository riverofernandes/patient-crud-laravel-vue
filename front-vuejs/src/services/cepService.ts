import { api } from "./api";
import { onlyDigits } from "@/utils/format";

/** GET /cep/{cep} result (ViaCEP proxy). Fields may be empty for city-wide CEPs. */
export interface CepResult {
  cep: string;
  street: string | null;
  neighborhood: string | null;
  city: string | null;
  state: string | null;
}

export const cepService = {
  /** 404 = unknown CEP, 503 = lookup service unavailable (fill in manually). */
  async lookup(
    cep: string,
    options: { signal?: AbortSignal } = {}
  ): Promise<CepResult> {
    const { data } = await api.get<{ data: CepResult }>(
      `/cep/${onlyDigits(cep)}`,
      { signal: options.signal }
    );
    return data.data;
  },
};
