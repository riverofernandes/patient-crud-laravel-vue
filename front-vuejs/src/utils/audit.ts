/**
 * Presentation of the patient change history (GET /patients/{id}/audits).
 */
import type { Audit, AuditEvent } from "@/services/patientService";
import { formatSex, isoToBr, maskCpf, maskPhone, maskRg } from "./format";

export const EMPTY_VALUE = "—";

export const AUDIT_EVENTS: Record<AuditEvent, { label: string; icon: string }> =
  {
    created: { label: "Cadastro", icon: "b-icon-person-plus" },
    updated: { label: "Edição", icon: "b-icon-pencil" },
    deleted: { label: "Exclusão", icon: "b-icon-trash" },
    restored: { label: "Restauração", icon: "b-icon-arrow-counterclockwise" },
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
];

const FORMATTERS: Record<string, (value: string) => string> = {
  cpf: maskCpf,
  rg: maskRg,
  phone: maskPhone,
  birth_date: isoToBr,
  sex: formatSex,
};

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
  return [...known, ...unknown].map(({ key, label }) => ({
    field: key,
    label,
    old: formatAuditValue(key, changes[key]?.old),
    new: formatAuditValue(key, changes[key]?.new),
  }));
}
