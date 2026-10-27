/**
 * Presentation of the patient change history (GET /patients/{id}/audits).
 */
import type { Audit, AuditEvent } from "@/services/patientService";
import { formatSex, isoToBr, maskCpf, maskPhone, maskRg } from "./format";
import { ADDRESS_FIELDS, maskCep } from "./address";

export const EMPTY_VALUE = "—";

export const AUDIT_EVENTS: Record<AuditEvent, { label: string; icon: string }> =
  {
    created: { label: "Cadastro", icon: "b-icon-person-plus" },
    updated: { label: "Edição", icon: "b-icon-pencil" },
    deleted: { label: "Exclusão", icon: "b-icon-trash" },
    restored: { label: "Restauração", icon: "b-icon-arrow-counterclockwise" },
    photo: { label: "Foto", icon: "b-icon-camera" },
  };

/** Same labels as the detail page, in the same order. */
export const AUDIT_FIELDS: ReadonlyArray<{ key: string; label: string }> = [
  { key: "name", label: "Nome completo" },
  { key: "cpf", label: "CPF" },
  { key: "rg", label: "RG" },
  { key: "birth_date", label: "Data de nascimento" },
  { key: "sex", label: "Sexo biológico" },
  { key: "phone", label: "Celular" },
  { key: "email", label: "E-mail" },
  { key: "description", label: "Descrição" },
  ...ADDRESS_FIELDS.map(({ key, label }) => ({
    key: `address.${key}`,
    label: `Endereço: ${label}`,
  })),
  { key: "photo", label: "Foto" },
];

/** Markers the API writes in `changes.photo.new` (the image never enters the history). */
const PHOTO_MARKERS: Record<string, string> = {
  adicionada: "Adicionada",
  alterada: "Alterada",
  removida: "Removida",
};

/** Known markers read as words; anything else (a version, a flag) as "Com foto". */
function formatPhoto(value: string): string {
  const marker = PHOTO_MARKERS[value.trim().toLowerCase()];
  if (marker) return marker;
  return /^(0|false|null)$/i.test(value) ? EMPTY_VALUE : "Com foto";
}

/**
 * One-word summary of a photo change ("Adicionada", "Alterada", "Removida"),
 * shown instead of "old → new" (the API sends `old: null` with a marker).
 */
export function photoChangeSummary(change: {
  old?: unknown;
  new?: unknown;
}): string {
  const value = change.new;
  if (value === null || value === undefined || String(value).trim() === "")
    return "Removida";
  const text = formatPhoto(String(value));
  if (text === EMPTY_VALUE) return "Removida";
  return text === "Com foto" ? "Alterada" : text;
}

const FORMATTERS: Record<string, (value: string) => string> = {
  cpf: maskCpf,
  rg: maskRg,
  phone: maskPhone,
  birth_date: isoToBr,
  sex: formatSex,
  "address.cep": maskCep,
  "address.state": (value) => value.toUpperCase(),
  photo: formatPhoto,
};

const DESCRIPTIONS: Record<string, string> = {
  created: "Paciente cadastrado.",
  updated: "Nenhum campo alterado.",
  deleted: "Paciente movido para a lixeira.",
  restored: "Paciente restaurado da lixeira.",
  photo: "Foto alterada.",
};

/**
 * Text shown when an entry has no changed-field list. For photo entries
 * (event "photo") the changes tell an upload from a removal when available.
 */
export function auditEventDescription(
  event: string,
  audit?: Pick<Audit, "changes">
): string {
  if (event === "photo" && audit) {
    const changes =
      audit.changes && !Array.isArray(audit.changes) ? audit.changes : {};
    const entry = changes.photo ?? Object.values(changes)[0];
    if (entry) {
      const summary = photoChangeSummary(entry);
      return summary === "Removida"
        ? "Foto removida."
        : summary === "Adicionada"
        ? "Foto adicionada."
        : "Foto atualizada.";
    }
  }
  return DESCRIPTIONS[event] ?? "";
}

export function auditEventLabel(event: string): string {
  return AUDIT_EVENTS[event as AuditEvent]?.label ?? event;
}

export function auditEventIcon(event: string): string {
  return AUDIT_EVENTS[event as AuditEvent]?.icon ?? "b-icon-clock-history";
}

/** Formats a raw value like the rest of the UI; empty values become "—". */
export function formatAuditValue(field: string, value: unknown): string {
  if (value === null || value === undefined) return EMPTY_VALUE;
  const text = String(value);
  if (text.trim() === "") return EMPTY_VALUE;
  const formatter = FORMATTERS[field];
  return (formatter ? formatter(text) : text) || text;
}

export interface FormattedChange {
  field: string;
  label: string;
  old: string;
  new: string;
  /** Replaces "old → new" when set (photo changes). */
  summary?: string;
}

/**
 * Changed fields in a stable order (the detail page's), formatted for display.
 * Unknown fields (added to the API later) go last with their raw key.
 */
export function auditChanges(audit: Pick<Audit, "changes">): FormattedChange[] {
  const changes =
    audit.changes && !Array.isArray(audit.changes) ? audit.changes : {};
  const known = AUDIT_FIELDS.filter(({ key }) => key in changes);
  const unknown = Object.keys(changes)
    .filter((key) => !AUDIT_FIELDS.some((f) => f.key === key))
    .map((key) => ({ key, label: key }));
  return [...known, ...unknown].map(({ key, label }) => {
    const change: FormattedChange = {
      field: key,
      label,
      old: formatAuditValue(key, changes[key]?.old),
      new: formatAuditValue(key, changes[key]?.new),
    };
    if (key === "photo")
      change.summary = photoChangeSummary(changes[key] ?? {});
    return change;
  });
}
