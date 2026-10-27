import { describe, expect, it } from "vitest";
import {
  MB,
  fileKind,
  fileType,
  formatFileSize,
  initials,
  progressPercent,
  validateAttachment,
  validatePhoto,
} from "../files";

const file = (name: string, type: string, size: number) => ({
  name,
  type,
  size,
});

describe("file helpers", () => {
  it("formats sizes in pt-BR (binary units)", () => {
    expect(formatFileSize(0)).toBe("0 B");
    expect(formatFileSize(820)).toBe("820 B");
    expect(formatFileSize(1536)).toBe("1,5 KB");
    expect(formatFileSize(183245)).toBe("179 KB");
    expect(formatFileSize(1.2 * MB)).toBe("1,2 MB");
    expect(formatFileSize(2 * MB)).toBe("2 MB");
    expect(formatFileSize(3 * 1024 * MB)).toBe("3 GB");
    expect(formatFileSize(undefined)).toBe("");
    expect(formatFileSize(NaN)).toBe("");
  });

  it("detects the type from the MIME or the extension", () => {
    expect(fileType(file("a.PDF", "", 1))).toBe("application/pdf");
    expect(fileType(file("a.jpeg", "", 1))).toBe("image/jpeg");
    expect(fileType(file("a", "", 1))).toBe("");
    expect(fileType(file("a.bin", "IMAGE/PNG", 1))).toBe("image/png");
  });

  it("validates the photo (type and 2 MB)", () => {
    expect(validatePhoto(file("a.webp", "image/webp", 1000))).toBe("");
    expect(validatePhoto(file("a.gif", "image/gif", 1000))).toBe(
      "Formato não suportado. Envie uma imagem JPEG, PNG ou WebP."
    );
    expect(validatePhoto(file("a.jpg", "image/jpeg", 2.5 * MB))).toBe(
      "A imagem tem 2,5 MB. O tamanho máximo é 2 MB."
    );
  });

  it("validates attachments (type, 10 MB, empty)", () => {
    expect(validateAttachment(file("a.pdf", "application/pdf", 10))).toBe("");
    expect(validateAttachment(file("a.webp", "image/webp", 10))).toBe(
      "Formato não suportado. Envie um arquivo PDF, JPEG ou PNG."
    );
    expect(validateAttachment(file("a.png", "image/png", 11 * MB))).toBe(
      "O arquivo tem 11 MB. O tamanho máximo é 10 MB."
    );
    expect(validateAttachment(file("a.png", "image/png", 0))).toBe(
      "O arquivo está vazio."
    );
  });

  it("builds initials from first and last names", () => {
    expect(initials("Ana Souza")).toBe("AS");
    expect(initials("maria da silva santos")).toBe("MS");
    expect(initials("João")).toBe("J");
    expect(initials("  ")).toBe("");
    expect(initials(null)).toBe("");
  });

  it("classifies kinds and computes progress", () => {
    expect(fileKind("application/pdf")).toBe("pdf");
    expect(fileKind("image/png")).toBe("image");
    expect(fileKind("text/plain")).toBe("other");
    expect(fileKind(null)).toBe("other");
    expect(progressPercent({ loaded: 50, total: 200 })).toBe(25);
    expect(progressPercent({ loaded: 50 })).toBe(0);
    expect(progressPercent({ loaded: 300, total: 200 })).toBe(100);
  });
});
