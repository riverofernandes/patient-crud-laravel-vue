/**
 * Ophthalmic exam (prontuário) rules: diopter parsing/formatting, the 0.25
 * steps, per-field validation (same rules as the API) and summaries.
 *
 * Diopters are shown with an explicit sign and two decimals (+1.25, −0.50,
 * 0.00), using the typographic minus sign (U+2212). Input accepts "," or "."
 * and an ASCII "-". Step math is done in integer quarters (no float drift).
 */
import type {
  Eye,
  EyeData,
  EyeExam,
  EyeExamPayload,
} from "@/services/consultationService";

export const MINUS = "−";
export const EYES: Eye[] = ["od", "oe"];
export const EYE_LABELS: Record<Eye, { short: string; long: string }> = {
  od: { short: "OD", long: "Olho direito" },
  oe: { short: "OE", long: "Olho esquerdo" },
};

export type DiopterField = "spherical" | "cylindrical" | "addition";

export const DIOPTER_RULES: Record<
  DiopterField,
  { min: number; max: number; label: string }
> = {
  spherical: { min: -30, max: 30, label: "Esférico" },
  cylindrical: { min: -10, max: 0, label: "Cilíndrico" },
  addition: { min: 0.75, max: 4, label: "Adição" },
};

export const AXIS_RANGE = { min: 0, max: 180 };
export const IOP_RANGE = { min: 5, max: 60 };
export const ACUITY_RANGE = { min: 10, max: 400 };

/** Form state of one eye: every value as typed (strings). */
export interface EyeForm {
  /** Only N of "20/N". */
  visual_acuity_sc: string;
  visual_acuity_cc: string;
  spherical: string;
  cylindrical: string;
  axis: string;
  addition: string;
  iop: string;
}

export type EyeField = keyof EyeForm;

export interface EyeExamForm {
  od: EyeForm;
  oe: EyeForm;
  diagnosis: string;
  observations: string;
}

/** Errors keyed like the API's 422 bag: "od.axis", "diagnosis"... */
export type EyeExamErrors = Record<string, string>;

/** Number from API values that may come as strings ("-1.25"). */
export function toNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null;
  const n = typeof value === "number" ? value : Number(value);
  return Number.isFinite(n) ? n : null;
}

/**
 * "−1,25", "-1.25", "+2", "1" -> number. Empty -> null. Garbage -> NaN.
 */
export function parseDiopter(text: string | null | undefined): number | null {
  const raw = (text ?? "")
    .trim()
    .replace(/\s+/g, "")
    .replace(/[−–]/g, "-")
    .replace(",", ".");
  if (raw === "") return null;
  if (!/^[+-]?(\d+(\.\d*)?|\.\d+)$/.test(raw)) return NaN;
  return Number(raw);
}

/** Whether a number is a multiple of 0.25. */
export function isQuarter(value: number): boolean {
  return Math.abs(value * 4 - Math.round(value * 4)) < 1e-9;
}

/** +1.25 / −0.50 / 0.00. Empty for null/invalid. */
export function formatDiopter(value: unknown): string {
  const n = toNumber(value);
  if (n === null) return "";
  if (n === 0) return "0.00";
  const text = Math.abs(n).toFixed(2);
  return n > 0 ? `+${text}` : `${MINUS}${text}`;
}

/** Re-formats what the user typed when it is a valid number; otherwise keeps it. */
export function normalizeDiopterText(text: string): string {
  const n = parseDiopter(text);
  return n === null || Number.isNaN(n) ? text.trim() : formatDiopter(n);
}

/**
 * One 0.25 step up (+1) or down (−1), clamped to the field's range. An empty
 * value steps from 0 (addition, whose range excludes 0, starts at +0.75).
 */
export function stepDiopter(
  text: string,
  direction: 1 | -1,
  field: DiopterField
): string {
  const { min, max } = DIOPTER_RULES[field];
  const current = parseDiopter(text);
  const empty = current === null || Number.isNaN(current);
  let quarters = empty ? direction : Math.round(current * 4) + direction;
  // Ranges that exclude 0 (addition) start at their first valid value.
  if (empty && min > 0) quarters = Math.round(min * 4);
  const clamped = Math.min(Math.max(quarters, min * 4), max * 4);
  return formatDiopter(clamped / 4);
}

/** Axis is only meaningful (and allowed) when the cylinder is not zero. */
export function axisEnabled(eye: Pick<EyeForm, "cylindrical">): boolean {
  const cyl = parseDiopter(eye.cylindrical);
  return cyl !== null && !Number.isNaN(cyl) && cyl !== 0;
}

/** "20/40" or "40" -> "40"; anything else stays as typed (digits only). */
export function acuityDenominator(value: string | null | undefined): string {
  const text = (value ?? "").trim();
  const match = /^20\s*\/\s*(\d*)$/.exec(text);
  return match ? match[1] : text.replace(/\D/g, "").slice(0, 3);
}

function emptyEye(): EyeForm {
  return {
    visual_acuity_sc: "",
    visual_acuity_cc: "",
    spherical: "",
    cylindrical: "",
    axis: "",
    addition: "",
    iop: "",
  };
}

function eyeToForm(eye: Partial<EyeData> | null | undefined): EyeForm {
  if (!eye) return emptyEye();
  const int = (v: unknown) => {
    const n = toNumber(v);
    return n === null ? "" : String(Math.round(n));
  };
  return {
    visual_acuity_sc: acuityDenominator(eye.visual_acuity_sc),
    visual_acuity_cc: acuityDenominator(eye.visual_acuity_cc),
    spherical: formatDiopter(eye.spherical),
    cylindrical: formatDiopter(eye.cylindrical),
    axis: int(eye.axis),
    addition: formatDiopter(eye.addition),
    iop: int(eye.iop),
  };
}

export function toEyeExamForm(exam: EyeExam | null | undefined): EyeExamForm {
  return {
    od: eyeToForm(exam?.od),
    oe: eyeToForm(exam?.oe),
    diagnosis: exam?.diagnosis ?? "",
    observations: exam?.observations ?? "",
  };
}

function formatRange(min: number, max: number): string {
  return `${formatDiopter(min)} e ${formatDiopter(max)}`;
}

function validateEye(eye: EyeForm, prefix: Eye, errors: EyeExamErrors): void {
  (["visual_acuity_sc", "visual_acuity_cc"] as const).forEach((field) => {
    const text = eye[field].trim();
    if (!text) return;
    const n = Number(text);
    // Same rule as the API: no leading zero ("20/040" is rejected).
    if (!/^[1-9]\d*$/.test(text) || n < ACUITY_RANGE.min || n > ACUITY_RANGE.max) {
      errors[`${prefix}.${field}`] = "Use 20/N, com N entre 10 e 400";
    }
  });

  (Object.keys(DIOPTER_RULES) as DiopterField[]).forEach((field) => {
    const value = parseDiopter(eye[field]);
    if (value === null) return;
    const { min, max } = DIOPTER_RULES[field];
    if (Number.isNaN(value)) {
      errors[`${prefix}.${field}`] = "Valor inválido";
    } else if (value < min || value > max) {
      errors[`${prefix}.${field}`] = `Entre ${formatRange(min, max)}`;
    } else if (!isQuarter(value)) {
      errors[`${prefix}.${field}`] = "Use passos de 0.25";
    }
  });

  const axisText = eye.axis.trim();
  if (axisEnabled(eye)) {
    const axis = Number(axisText);
    if (!axisText) {
      errors[`${prefix}.axis`] = "Informe o eixo";
    } else if (
      !/^\d+$/.test(axisText) ||
      axis < AXIS_RANGE.min ||
      axis > AXIS_RANGE.max
    ) {
      errors[`${prefix}.axis`] = "Inteiro entre 0 e 180";
    }
  }

  const iopText = eye.iop.trim();
  if (iopText) {
    const iop = Number(iopText);
    if (!/^\d+$/.test(iopText) || iop < IOP_RANGE.min || iop > IOP_RANGE.max) {
      errors[`${prefix}.iop`] = "Inteiro entre 5 e 60 mmHg";
    }
  }
}

export function validateEyeExamForm(form: EyeExamForm): EyeExamErrors {
  const errors: EyeExamErrors = {};
  EYES.forEach((eye) => validateEye(form[eye], eye, errors));
  if (form.diagnosis.length > 2000)
    errors.diagnosis = "Máximo de 2000 caracteres";
  if (form.observations.length > 5000)
    errors.observations = "Máximo de 5000 caracteres";
  return errors;
}

function eyeToPayload(eye: EyeForm): EyeData {
  const diopter = (text: string) => {
    const n = parseDiopter(text);
    return n === null || Number.isNaN(n) ? null : n;
  };
  const int = (text: string) => (text.trim() ? Number(text.trim()) : null);
  const acuity = (text: string) => (text.trim() ? `20/${text.trim()}` : null);
  const cylindrical = diopter(eye.cylindrical);
  return {
    visual_acuity_sc: acuity(eye.visual_acuity_sc),
    visual_acuity_cc: acuity(eye.visual_acuity_cc),
    spherical: diopter(eye.spherical),
    cylindrical,
    // Forbidden by the API when the cylinder is 0 / absent.
    axis: axisEnabled(eye) ? int(eye.axis) : null,
    addition: diopter(eye.addition),
    iop: int(eye.iop),
  };
}

export function toEyeExamPayload(form: EyeExamForm): EyeExamPayload {
  return {
    od: eyeToPayload(form.od),
    oe: eyeToPayload(form.oe),
    diagnosis: form.diagnosis.trim() || null,
    observations: form.observations.trim() || null,
  };
}

/** Whether any refraction value exists (needed for the prescription PDF). */
export function hasRefraction(exam: EyeExam | null | undefined): boolean {
  if (!exam) return false;
  return EYES.some((eye) =>
    (["spherical", "cylindrical", "addition"] as const).some(
      (field) => toNumber(exam[eye]?.[field]) !== null
    )
  );
}

/** "−1.25 −0.50 × 180 Ad +2.00" for one eye ('' when empty). */
export function eyeRefraction(
  eye: Partial<EyeData> | null | undefined
): string {
  if (!eye) return "";
  const parts: string[] = [];
  const sph = toNumber(eye.spherical);
  const cyl = toNumber(eye.cylindrical);
  const add = toNumber(eye.addition);
  if (sph !== null) parts.push(formatDiopter(sph));
  if (cyl !== null && cyl !== 0) {
    if (sph === null) parts.push("0.00");
    const axis = toNumber(eye.axis);
    parts.push(
      `${formatDiopter(cyl)}${axis !== null ? ` × ${Math.round(axis)}°` : ""}`
    );
  }
  if (add !== null) parts.push(`Ad ${formatDiopter(add)}`);
  return parts.join(" ");
}

/** Compact summary for lists: "OD −1.25 −0.50 × 180° · OE −1.00". */
export function refractionSummary(exam: EyeExam | null | undefined): string {
  if (!exam) return "";
  return EYES.map((eye) => {
    const text = eyeRefraction(exam[eye]);
    return text ? `${EYE_LABELS[eye].short} ${text}` : "";
  })
    .filter(Boolean)
    .join(" · ");
}

/** Maps API 422 keys ("od.axis", "oe.spherical", "diagnosis") to messages. */
export function mapEyeExamErrors(
  errors: Record<string, string[]> | null
): EyeExamErrors {
  const mapped: EyeExamErrors = {};
  Object.entries(errors ?? {}).forEach(([key, messages]) => {
    if (messages?.length)
      mapped[key.replace(/\[(\w+)\]/g, ".$1")] = messages[0];
  });
  return mapped;
}

/** DOM id of a field: "od.spherical" -> "ee-od-spherical". */
export function eyeExamFieldId(key: string): string {
  return `ee-${key.replace(/[._]/g, "-")}`;
}
