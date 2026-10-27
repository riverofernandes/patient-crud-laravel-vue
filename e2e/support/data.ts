/** Test data generators. Everything is random so tests never depend on the seed. */

const LETTERS = "abcdefghijklmnopqrstuvwxyz";

function randomInt(max: number): number {
  return Math.floor(Math.random() * max);
}

/** Random lowercase letters (no digits, so a search term never hits the CPF column). */
export function randomLetters(length: number): string {
  return Array.from({ length }, () => LETTERS[randomInt(LETTERS.length)]).join("");
}

function checkDigit(digits: number[]): number {
  const weightStart = digits.length + 1;
  const sum = digits.reduce((acc, d, i) => acc + d * (weightStart - i), 0);
  const rest = (sum * 10) % 11;
  return rest === 10 ? 0 : rest;
}

/** A valid CPF (11 digits, no mask) with random base digits. */
export function generateCpf(): string {
  let base: number[];
  do {
    base = Array.from({ length: 9 }, () => randomInt(10));
  } while (base.every((d) => d === base[0]));
  const d1 = checkDigit(base);
  const d2 = checkDigit([...base, d1]);
  return [...base, d1, d2].join("");
}

export function maskCpf(cpf: string): string {
  return cpf.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
}

export function maskPhone(phone: string): string {
  return phone.length === 11
    ? phone.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3")
    : phone.replace(/^(\d{2})(\d{4})(\d{4})$/, "($1) $2-$3");
}

/** "1990-01-01" -> "01/01/1990" (pure string conversion, no Date/timezone involved). */
export function isoToBr(iso: string): string {
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
}

/** Unique, letter-only tag used to prefix patient names created by a test. */
export function uniqueTag(): string {
  return `E2E ${randomLetters(1).toUpperCase()}${randomLetters(9)}`;
}

/** Patient address (Sprint 5). `cep` is stored as 8 digits; `state` in upper case. */
export interface Address {
  cep: string;
  street: string;
  number: string;
  complement: string | null;
  neighborhood: string;
  city: string;
  state: string;
}

/** The CEP mock's address (Avenida Paulista), completed with a number and a complement. */
export function buildAddress(overrides: Partial<Address> = {}): Address {
  return {
    cep: "01310100",
    street: "Avenida Paulista",
    number: "1578",
    complement: "Sala 12",
    neighborhood: "Bela Vista",
    city: "São Paulo",
    state: "SP",
    ...overrides,
  };
}

export interface PatientPayload {
  name: string;
  cpf: string;
  rg: string;
  birth_date: string;
  sex: "M" | "F";
  phone: string;
  email: string | null;
  description: string | null;
  /** Optional (Sprint 5); omitted or null = no address. */
  address?: Address | null;
}

export function buildPatient(overrides: Partial<PatientPayload> = {}): PatientPayload {
  return {
    name: `${uniqueTag()} Paciente`,
    cpf: generateCpf(),
    rg: String(100000000 + randomInt(899999999)),
    // Jan 1st: an off-by-one-day bug would also flip the year.
    birth_date: "1990-01-01",
    sex: "F",
    phone: `119${String(10000000 + randomInt(89999999))}`,
    email: null,
    description: null,
    ...overrides,
  };
}

/**
 * Unique professional name ("Dr. E2E Xqzkabcdef"). Conflict checks and the agenda filter are per
 * professional, so a unique name isolates a test's consultations from the seed and other tests.
 * Filter the agenda by `tokenOf(name)` (the random part), never by "E2E" (shared by every test).
 */
export function professionalName(title = "Dr."): string {
  return `${title} ${uniqueTag()}`;
}

/** The random 10-letter token of a name built with `uniqueTag()`. */
export function tokenOf(name: string): string {
  const match = /E2E ([A-Z][a-z]{9})/.exec(name);
  if (!match) throw new Error(`No E2E token in "${name}"`);
  return match[1];
}
