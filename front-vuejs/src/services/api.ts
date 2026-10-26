import axios, { AxiosError } from "axios";

export const API_URL: string = (
  import.meta.env.VITE_API_URL || "http://localhost:8000/api"
).replace(/\/+$/, "");

export const api = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  timeout: 15000,
});

export type ValidationErrors = Record<string, string[]>;

interface ErrorBody {
  message?: string;
  errors?: ValidationErrors;
}

export function getStatus(error: unknown): number | undefined {
  return axios.isAxiosError(error) ? error.response?.status : undefined;
}

export function isNotFound(error: unknown): boolean {
  return getStatus(error) === 404;
}

/** Returns Laravel's 422 `errors` bag, or null for any other error. */
export function getValidationErrors(error: unknown): ValidationErrors | null {
  if (getStatus(error) !== 422) return null;
  return (error as AxiosError<ErrorBody>).response?.data?.errors ?? {};
}

export function getErrorMessage(
  error: unknown,
  fallback = "Ocorreu um erro inesperado."
): string {
  if (axios.isAxiosError(error)) {
    if (!error.response) {
      return error.code === "ECONNABORTED"
        ? "O servidor demorou para responder. Tente novamente."
        : "Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.";
    }
    const { status } = error.response;
    if (status === 429)
      return "Muitas requisições. Tente novamente em instantes.";
    if (status >= 500)
      return "O servidor encontrou um erro. Tente novamente em instantes.";
    const data = error.response.data as ErrorBody | undefined;
    if (data?.message) return data.message;
  }
  return fallback;
}
