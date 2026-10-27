/**
 * File helpers for the patient photo and consultation attachments (Sprint 5).
 */

export const MB = 1024 * 1024;

export const PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"] as const;
export const PHOTO_ACCEPT = PHOTO_TYPES.join(",");
export const PHOTO_MAX_BYTES = 2 * MB;

export const ATTACHMENT_TYPES = [
  "application/pdf",
  "image/jpeg",
  "image/png",
] as const;
export const ATTACHMENT_ACCEPT = `${ATTACHMENT_TYPES.join(
  ","
)},.pdf,.jpg,.jpeg,.png`;
export const ATTACHMENT_MAX_BYTES = 10 * MB;

const EXTENSION_TYPES: Record<string, string> = {
  pdf: "application/pdf",
  jpg: "image/jpeg",
  jpeg: "image/jpeg",
  png: "image/png",
  webp: "image/webp",
};

/** MIME type of a file, falling back to its extension when the browser gives none. */
export function fileType(file: Pick<File, "name" | "type">): string {
  if (file.type) return file.type.toLowerCase();
  const ext = /\.([a-z0-9]+)$/i.exec(file.name)?.[1]?.toLowerCase() ?? "";
  return EXTENSION_TYPES[ext] ?? "";
}

const decimal = (value: number, digits: number) =>
  value.toLocaleString("pt-BR", {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  });

/** 183245 -> "179 KB", 1258291 -> "1,2 MB" (binary units, pt-BR decimals). */
export function formatFileSize(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || !Number.isFinite(bytes))
    return "";
  if (bytes < 1024) return `${Math.max(0, Math.round(bytes))} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${decimal(kb, kb < 10 ? 1 : 0)} KB`;
  const mb = kb / 1024;
  if (mb < 1024) return `${decimal(mb, 1)} MB`;
  return `${decimal(mb / 1024, 1)} GB`;
}

/** Client checks for the patient photo; '' when the file is acceptable. */
export function validatePhoto(
  file: Pick<File, "name" | "type" | "size">
): string {
  if (!(PHOTO_TYPES as readonly string[]).includes(fileType(file)))
    return "Formato não suportado. Envie uma imagem JPEG, PNG ou WebP.";
  if (file.size > PHOTO_MAX_BYTES)
    return `A imagem tem ${formatFileSize(
      file.size
    )}. O tamanho máximo é 2 MB.`;
  return "";
}

/** Client checks for an attachment; '' when the file is acceptable. */
export function validateAttachment(
  file: Pick<File, "name" | "type" | "size">
): string {
  if (!(ATTACHMENT_TYPES as readonly string[]).includes(fileType(file)))
    return "Formato não suportado. Envie um arquivo PDF, JPEG ou PNG.";
  if (file.size > ATTACHMENT_MAX_BYTES)
    return `O arquivo tem ${formatFileSize(
      file.size
    )}. O tamanho máximo é 10 MB.`;
  if (file.size === 0) return "O arquivo está vazio.";
  return "";
}

/** Initials for the avatar fallback: first and last names ("Ana de Souza" -> "AS"). */
export function initials(name: string | null | undefined): string {
  const all = (name ?? "")
    .trim()
    .split(/\s+/)
    .filter((w) => w && !/^(d[aeo]s?|e)$/i.test(w));
  // Prefer words that start with a letter ("Ana 2" -> "A", not "A2").
  const letters = all.filter((w) => /^\p{L}/u.test(w));
  const words = letters.length ? letters : all;
  if (!words.length) return "";
  const first = words[0][0];
  const last = words.length > 1 ? words[words.length - 1][0] : "";
  return `${first}${last}`.toUpperCase();
}

export type FileKind = "pdf" | "image" | "other";

export function fileKind(mimeType: string | null | undefined): FileKind {
  const type = (mimeType ?? "").toLowerCase();
  if (type === "application/pdf") return "pdf";
  if (type.startsWith("image/")) return "image";
  return "other";
}

/** Upload progress (0–100) from an axios progress event. */
export function progressPercent(event: {
  loaded: number;
  total?: number;
}): number {
  if (!event.total) return 0;
  return Math.min(100, Math.round((event.loaded / event.total) * 100));
}
