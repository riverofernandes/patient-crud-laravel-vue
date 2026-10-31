import type Vue from "vue";
import { patientService, type Patient } from "@/services/patientService";
import { getErrorMessage, isNotFound } from "@/services/api";
import { notify } from "./notify";

/** Emitted on the root instance after a patient comes back from the trash. */
export const PATIENT_RESTORED_EVENT = "patient:restored";

/**
 * "Paciente restaurado com sucesso!", plus how many future consultations the
 * API cancelled because their slot was taken while the patient was in the trash.
 */
export function restoreSuccessMessage(cancelled: number): string {
  const base = "Paciente restaurado com sucesso!";
  if (cancelled <= 0) return base;
  return cancelled === 1
    ? `${base} 1 atendimento futuro foi cancelado por conflito de horário.`
    : `${base} ${cancelled} atendimentos futuros foram cancelados por conflito de horário.`;
}

export type RestoreResult = "restored" | "not-found" | "failed";

/**
 * Restores a soft-deleted patient and shows the outcome as a toast
 * (409 = another active patient already uses the CPF: the API message is shown).
 */
export async function restorePatient(
  rootInstance: object,
  patient: Pick<Patient, "id" | "name">
): Promise<RestoreResult> {
  const root = rootInstance as Vue;
  try {
    const { patient: restored, cancelledConsultations } =
      await patientService.restore(patient.id);
    notify(
      root,
      "success",
      restoreSuccessMessage(cancelledConsultations.length)
    );
    root.$emit(PATIENT_RESTORED_EVENT, restored);
    return "restored";
  } catch (error) {
    if (isNotFound(error)) {
      notify(root, "error", "Este paciente não está mais na lixeira.");
      return "not-found";
    }
    notify(
      root,
      "error",
      getErrorMessage(error, "Não foi possível restaurar o paciente.")
    );
    return "failed";
  }
}
