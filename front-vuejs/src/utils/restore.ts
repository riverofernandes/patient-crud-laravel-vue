import type Vue from "vue";
import { patientService, type Patient } from "@/services/patientService";
import { getErrorMessage, isNotFound } from "@/services/api";
import { notify } from "./notify";

/** Emitted on the root instance after a patient comes back from the trash. */
export const PATIENT_RESTORED_EVENT = "patient:restored";

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
    const restored = await patientService.restore(patient.id);
    notify(root, "success", "Paciente restaurado com sucesso!");
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
