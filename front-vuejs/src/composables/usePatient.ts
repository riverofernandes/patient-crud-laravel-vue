import { ref, watch, type Ref } from "vue";
import { patientService, type Patient } from "@/services/patientService";
import { getErrorMessage, isNotFound } from "@/services/api";

/** Loads a single patient by id, tracking loading / not-found / error states. */
export function usePatient(id: Ref<string | number>) {
  const patient = ref<Patient | null>(null);
  const loading = ref(true);
  const notFound = ref(false);
  const error = ref("");

  async function load(): Promise<void> {
    loading.value = true;
    notFound.value = false;
    error.value = "";
    try {
      patient.value = await patientService.get(id.value);
    } catch (e) {
      patient.value = null;
      if (isNotFound(e)) notFound.value = true;
      else
        error.value = getErrorMessage(
          e,
          "Não foi possível carregar o paciente."
        );
    } finally {
      loading.value = false;
    }
  }

  watch(id, load, { immediate: true });

  return { patient, loading, notFound, error, load };
}
