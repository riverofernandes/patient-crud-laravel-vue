import { onlyDigits } from "./format";

/** Validates a CPF using its two check digits. */
export function isValidCpf(value: string | null | undefined): boolean {
  const cpf = onlyDigits(value);
  if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

  const checkDigit = (length: number): number => {
    let sum = 0;
    for (let i = 0; i < length; i++) {
      sum += Number(cpf[i]) * (length + 1 - i);
    }
    const rest = (sum * 10) % 11;
    return rest === 10 ? 0 : rest;
  };

  return checkDigit(9) === Number(cpf[9]) && checkDigit(10) === Number(cpf[10]);
}

export function isValidEmail(value: string | null | undefined): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((value ?? "").trim());
}
