/**
 * Patient address (Sprint 5): form state, client validation mirroring the
 * API contract, payload and display helpers.
 */
import type { Address } from "@/services/patientService";
import { onlyDigits } from "./format";

/** The 27 Brazilian federative units, alphabetical by abbreviation. */
export const UFS: ReadonlyArray<{ value: string; text: string }> = [
  { value: "AC", text: "Acre" },
  { value: "AL", text: "Alagoas" },
  { value: "AM", text: "Amazonas" },
  { value: "AP", text: "Amapá" },
  { value: "BA", text: "Bahia" },
  { value: "CE", text: "Ceará" },
  { value: "DF", text: "Distrito Federal" },
  { value: "ES", text: "Espírito Santo" },
  { value: "GO", text: "Goiás" },
  { value: "MA", text: "Maranhão" },
  { value: "MG", text: "Minas Gerais" },
  { value: "MS", text: "Mato Grosso do Sul" },
  { value: "MT", text: "Mato Grosso" },
  { value: "PA", text: "Pará" },
  { value: "PB", text: "Paraíba" },
  { value: "PE", text: "Pernambuco" },
  { value: "PI", text: "Piauí" },
  { value: "PR", text: "Paraná" },
  { value: "RJ", text: "Rio de Janeiro" },
  { value: "RN", text: "Rio Grande do Norte" },
  { value: "RO", text: "Rondônia" },
  { value: "RR", text: "Roraima" },
  { value: "RS", text: "Rio Grande do Sul" },
  { value: "SC", text: "Santa Catarina" },
  { value: "SE", text: "Sergipe" },
  { value: "SP", text: "São Paulo" },
  { value: "TO", text: "Tocantins" },
];

export type AddressField = keyof Address;

/** Field order (form, history) and labels. */
export const ADDRESS_FIELDS: ReadonlyArray<{
  key: AddressField;
  label: string;
}> = [
  { key: "cep", label: "CEP" },
  { key: "street", label: "Rua" },
  { key: "number", label: "Número" },
  { key: "complement", label: "Complemento" },
  { key: "neighborhood", label: "Bairro" },
  { key: "city", label: "Cidade" },
  { key: "state", label: "UF" },
];

/** Max lengths from the contract (cep is the masked length). */
export const ADDRESS_MAX: Record<AddressField, number> = {
  cep: 9,
  street: 255,
  number: 20,
  complement: 100,
  neighborhood: 100,
  city: 100,
  state: 2,
};

const REQUIRED_FIELDS: AddressField[] = [
  "cep",
  "street",
  "number",
  "neighborhood",
  "city",
  "state",
];

export type AddressForm = Record<AddressField, string>;
export type AddressErrors = Partial<Record<AddressField, string>>;

/** 00000000 -> 00000-000 (progressive). */
export function maskCep(value: string | null | undefined): string {
  const d = onlyDigits(value).slice(0, 8);
  return d.length <= 5 ? d : `${d.slice(0, 5)}-${d.slice(5)}`;
}

export function toAddressForm(address?: Address | null): AddressForm {
  return {
    cep: maskCep(address?.cep),
    street: address?.street ?? "",
    number: address?.number ?? "",
    complement: address?.complement ?? "",
    neighborhood: address?.neighborhood ?? "",
    city: address?.city ?? "",
    state: (address?.state ?? "").toUpperCase(),
  };
}

export function isAddressEmpty(form: AddressForm): boolean {
  return ADDRESS_FIELDS.every(({ key }) => !form[key].trim());
}

/** Once any field is filled, every field but the complement is required. */
export function validateAddress(form: AddressForm): {
  errors: AddressErrors;
  missingRequired: boolean;
} {
  const errors: AddressErrors = {};
  let missingRequired = false;
  if (isAddressEmpty(form)) return { errors, missingRequired };
  REQUIRED_FIELDS.forEach((key) => {
    if (!form[key].trim()) {
      errors[key] = "Campo obrigatório";
      missingRequired = true;
    }
  });
  if (!errors.cep && onlyDigits(form.cep).length !== 8) {
    errors.cep = "CEP inválido";
  }
  if (!errors.state && !UFS.some((uf) => uf.value === form.state)) {
    errors.state = "UF inválida";
  }
  return { errors, missingRequired };
}

/** API payload: `null` when every field is empty (removes the address). */
export function toAddressPayload(form: AddressForm): Address | null {
  if (isAddressEmpty(form)) return null;
  return {
    cep: onlyDigits(form.cep),
    street: form.street.trim(),
    number: form.number.trim(),
    complement: form.complement.trim() || null,
    neighborhood: form.neighborhood.trim(),
    city: form.city.trim(),
    state: form.state.trim().toUpperCase(),
  };
}

/**
 * One-line address as in the CSV export ("rua, número - complemento - bairro"),
 * followed by "cidade/UF" and the CEP. '' when there is no address.
 */
export function formatAddress(address?: Address | null): string {
  if (!address) return "";
  const line = [
    [address.street, address.number].filter(Boolean).join(", "),
    address.complement,
    address.neighborhood,
  ]
    .filter(Boolean)
    .join(" - ");
  const place = [address.city, address.state].filter(Boolean).join("/");
  const cep = address.cep ? `CEP ${maskCep(address.cep)}` : "";
  return [line, place, cep].filter(Boolean).join(" · ");
}

/** "address.cep" -> "cep" for keys of the 422 errors bag; null otherwise. */
export function addressErrorField(key: string): AddressField | null {
  if (key === "address") return "cep";
  const match = /^address\.(\w+)$/.exec(key);
  if (!match) return null;
  const field = match[1] as AddressField;
  return field in ADDRESS_MAX ? field : null;
}
