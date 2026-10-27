import { readFileSync } from "node:fs";
import { join } from "node:path";
import type { Page, Route } from "@playwright/test";
import type { UploadFile } from "./api";

/** Sample files shipped with the API docs (php-laravel/postman/fixtures). */
const POSTMAN_FIXTURES = join(__dirname, "..", "..", "php-laravel", "postman", "fixtures");
/** Images generated for the E2E suite (see fixtures/generate.php). */
const E2E_FIXTURES = join(__dirname, "..", "fixtures");

function load(dir: string, file: string, mimeType: string, name = file): UploadFile {
  return { name, mimeType, buffer: readFileSync(join(dir, file)) };
}

/** Valid 160x160 PNG. */
export const photoPng = (name = "foto.png"): UploadFile => load(POSTMAN_FIXTURES, "photo.png", "image/png", name);
/** Valid one-page PDF. */
export const examPdf = (name = "exame.pdf"): UploadFile => load(POSTMAN_FIXTURES, "exam.pdf", "application/pdf", name);
/** Plain text with an image extension. */
export const notAnImage = (name = "not-an-image.jpg", mimeType = "image/jpeg"): UploadFile =>
  load(POSTMAN_FIXTURES, "not-an-image.jpg", mimeType, name);
/** 50x50 PNG (below the 100x100 minimum). */
export const tinyPng = (): UploadFile => load(E2E_FIXTURES, "tiny-50x50.png", "image/png");
/** 900x600 PNG (non-square, larger than the 512x512 the server keeps). */
export const widePng = (): UploadFile => load(E2E_FIXTURES, "wide-900x600.png", "image/png");
/** 300x300 JPEG with EXIF (Make "E2ECam" + GPS). */
export const exifJpeg = (): UploadFile => load(E2E_FIXTURES, "exif-gps.jpg", "image/jpeg");

/** A buffer of `bytes` bytes (content irrelevant: only the client-side size check sees it). */
export function bigFile(name: string, mimeType: string, bytes: number): UploadFile {
  return { name, mimeType, buffer: Buffer.alloc(bytes, 0x20) };
}

export interface JpegInfo {
  width: number;
  height: number;
  /** Marker bytes of every segment before the scan (0xE0 = APP0/JFIF, 0xE1 = APP1/EXIF or XMP, ...). */
  markers: number[];
}

/** Walks the JPEG segments up to the first SOF and returns its size; throws if not a JPEG. */
export function jpegInfo(bytes: Buffer): JpegInfo {
  if (bytes[0] !== 0xff || bytes[1] !== 0xd8) throw new Error("not a JPEG (no SOI)");
  const markers: number[] = [];
  let i = 2;
  while (i + 4 <= bytes.length) {
    if (bytes[i] !== 0xff) throw new Error(`bad JPEG segment at ${i}`);
    const marker = bytes[i + 1];
    markers.push(marker);
    const length = bytes.readUInt16BE(i + 2);
    // SOF0..SOF15 except DHT (C4), JPG (C8) and DAC (CC).
    if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) {
      return { height: bytes.readUInt16BE(i + 5), width: bytes.readUInt16BE(i + 7), markers };
    }
    i += 2 + length;
  }
  throw new Error("no SOF segment");
}

/* ---------- CEP lookup mock (never the real ViaCEP) ---------- */

export const CEP_FOUND_BODY = {
  data: { cep: "01310100", street: "Avenida Paulista", neighborhood: "Bela Vista", city: "São Paulo", state: "SP" },
};
export const CEP_NOT_FOUND_BODY = { message: "CEP não encontrado." };
export const CEP_UNAVAILABLE_BODY = { message: "Serviço de CEP indisponível. Preencha o endereço manualmente." };

/**
 * Answers GET /api/cep/{cep} in the browser with `status`/`body` and returns the list of looked-up CEPs
 * (one entry per request that reached the mock).
 */
export async function mockCep(page: Page, status: 200 | 404 | 503, body: unknown): Promise<string[]> {
  const calls: string[] = [];
  await page.route("**/api/cep/**", async (route: Route) => {
    const cors = {
      "access-control-allow-origin": "*",
      "access-control-allow-headers": "*",
      "access-control-allow-methods": "GET, OPTIONS",
    };
    if (route.request().method() === "OPTIONS") {
      await route.fulfill({ status: 204, headers: cors });
      return;
    }
    calls.push(new URL(route.request().url()).pathname.split("/").pop() ?? "");
    await route.fulfill({ status, json: body, headers: cors });
  });
  return calls;
}
