import type { Consultation, EyeExam } from "@/services/consultationService";

export const exam: EyeExam = {
  od: {
    visual_acuity_sc: "20/40",
    visual_acuity_cc: "20/20",
    spherical: -1.25,
    cylindrical: -0.5,
    axis: 180,
    addition: null,
    iop: 14,
  },
  oe: {
    visual_acuity_sc: "20/50",
    visual_acuity_cc: "20/20",
    spherical: -1,
    cylindrical: 0,
    axis: null,
    addition: null,
    iop: 15,
  },
  diagnosis: "Miopia leve.",
  observations: null,
  updated_at: "2026-11-03T15:00:00-03:00",
};

export function makeConsultation(
  overrides: Partial<Consultation> = {}
): Consultation {
  return {
    id: 11,
    patient_id: 7,
    patient: { id: 7, name: "Ana Souza" },
    scheduled_at: "2026-11-03T14:30:00-03:00",
    duration_minutes: 30,
    status: "scheduled",
    professional_name: "Dra. Helena Prado",
    professional_crm: "123456/SP",
    reason: "Visão embaçada",
    notes: null,
    eye_exam: null,
    created_at: "2026-11-01T10:00:00-03:00",
    updated_at: "2026-11-01T10:00:00-03:00",
    ...overrides,
  };
}

/** An axios-like 422 error. */
export function validationError(errors: Record<string, string[]>) {
  return Object.assign(new Error("422"), {
    isAxiosError: true,
    response: { status: 422, data: { message: "x", errors } },
  });
}
