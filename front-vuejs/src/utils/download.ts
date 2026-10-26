/**
 * File download helpers (CSV export).
 */

/**
 * Filename from a Content-Disposition header. Prefers the RFC 5987
 * `filename*=UTF-8''...` form, then `filename="..."` / `filename=...`.
 * Returns '' when absent; path separators are stripped.
 */
export function filenameFromContentDisposition(
  header: string | null | undefined
): string {
  if (!header) return "";
  let name = "";
  const extended = /filename\*\s*=\s*([^']*)'[^']*'([^;]+)/i.exec(header);
  if (extended) {
    try {
      name = decodeURIComponent(extended[2].trim().replace(/^"|"$/g, ""));
    } catch {
      name = "";
    }
  }
  if (!name) {
    const plain = /filename\s*=\s*(?:"([^"]*)"|([^;]+))/i.exec(header);
    name = (plain?.[1] ?? plain?.[2] ?? "").trim();
  }
  return name.replace(/[/\\]/g, "_");
}

/** pacientes-AAAAMMDD-HHMM.csv in local time (same pattern as the API). */
export function exportFilename(date: Date = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `pacientes-${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(
    date.getDate()
  )}-${pad(date.getHours())}${pad(date.getMinutes())}.csv`;
}

/** Saves a Blob through a temporary object URL and <a download>. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.rel = "noopener";
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  link.remove();
  // Some browsers start the download asynchronously: revoke on a later tick.
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
