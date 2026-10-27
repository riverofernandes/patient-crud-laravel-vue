/**
 * Consultation status: labels, badge styling, the transition table of the API
 * and the actions (buttons) each status allows. Single source for the detail
 * page, the patient tabs and the agenda.
 */
import type { ConsultationStatus } from "@/services/consultationService";

export interface StatusMeta {
  label: string;
  /** Registered BootstrapVue icon (the label is always shown too). */
  icon: string;
}

export const STATUS_META: Record<ConsultationStatus, StatusMeta> = {
  scheduled: { label: "Agendado", icon: "b-icon-clock" },
  confirmed: { label: "Confirmado", icon: "b-icon-calendar-check" },
  completed: { label: "Realizado", icon: "b-icon-check-circle" },
  no_show: { label: "Faltou", icon: "b-icon-person-x" },
  cancelled: { label: "Cancelado", icon: "b-icon-x-circle" },
};

export const STATUS_ORDER: ConsultationStatus[] = [
  "scheduled",
  "confirmed",
  "completed",
  "no_show",
  "cancelled",
];

/** API transition table (anything else → 422). */
export const TRANSITIONS: Record<ConsultationStatus, ConsultationStatus[]> = {
  scheduled: ["confirmed", "completed", "no_show", "cancelled"],
  confirmed: ["completed", "no_show", "cancelled"],
  completed: [],
  no_show: [],
  cancelled: [],
};

export function isConsultationStatus(
  value: unknown
): value is ConsultationStatus {
  return typeof value === "string" && value in STATUS_META;
}

export function statusLabel(status: string): string {
  return isConsultationStatus(status) ? STATUS_META[status].label : status;
}

export function statusIcon(status: string): string {
  return isConsultationStatus(status)
    ? STATUS_META[status].icon
    : "b-icon-circle";
}

export function canTransition(from: string, to: string): boolean {
  return (
    isConsultationStatus(from) &&
    isConsultationStatus(to) &&
    TRANSITIONS[from].includes(to)
  );
}

/** completed / no_show / cancelled: only `notes` can change. */
export function isFinalStatus(status: string): boolean {
  return isConsultationStatus(status) && TRANSITIONS[status].length === 0;
}

export interface StatusActionConfirm {
  title: string;
  message: string;
  confirmLabel: string;
}

export interface StatusAction {
  target: ConsultationStatus;
  label: string;
  variant: "primary" | "outline-primary" | "outline-danger";
  icon: string;
  /** Irreversible actions ask first. */
  confirm?: StatusActionConfirm;
  success: string;
}

const ACTIONS: Record<
  Exclude<ConsultationStatus, "scheduled">,
  Omit<StatusAction, "target">
> = {
  confirmed: {
    label: "Confirmar",
    variant: "outline-primary",
    icon: "b-icon-calendar-check",
    success: "Atendimento confirmado.",
  },
  completed: {
    label: "Marcar como realizado",
    variant: "primary",
    icon: "b-icon-check-circle",
    success: "Atendimento marcado como realizado.",
  },
  no_show: {
    label: "Registrar falta",
    variant: "outline-danger",
    icon: "b-icon-person-x",
    confirm: {
      title: "Registrar falta",
      message:
        "Confirma que o paciente não compareceu a este atendimento? Esta ação não pode ser desfeita.",
      confirmLabel: "Registrar falta",
    },
    success: "Falta registrada.",
  },
  cancelled: {
    label: "Cancelar atendimento",
    variant: "outline-danger",
    icon: "b-icon-x-circle",
    confirm: {
      title: "Cancelar atendimento",
      message:
        "Deseja realmente cancelar este atendimento? Esta ação não pode ser desfeita e o horário fica livre para outro agendamento.",
      confirmLabel: "Cancelar atendimento",
    },
    success: "Atendimento cancelado.",
  },
};

/** Buttons allowed for a status, in the transition table's order. */
export function allowedActions(status: string): StatusAction[] {
  if (!isConsultationStatus(status)) return [];
  return TRANSITIONS[status].map((target) => ({
    target,
    ...ACTIONS[target as keyof typeof ACTIONS],
  }));
}
