import { describe, expect, it } from "vitest";
import {
  auditChanges,
  auditEventDescription,
  auditEventIcon,
  auditEventLabel,
  formatAuditValue,
} from "../audit";

describe("audit formatting", () => {
  it("labels events", () => {
    expect(auditEventLabel("created")).toBe("Cadastro");
    expect(auditEventLabel("updated")).toBe("Edição");
    expect(auditEventLabel("deleted")).toBe("Exclusão");
    expect(auditEventLabel("restored")).toBe("Restauração");
    expect(auditEventLabel("other")).toBe("other");
    expect(auditEventIcon("deleted")).toBe("b-icon-trash");
    expect(auditEventIcon("other")).toBe("b-icon-clock-history");
  });

  it("formats values like the UI", () => {
    expect(formatAuditValue("cpf", "52998224725")).toBe("529.982.247-25");
    expect(formatAuditValue("rg", "123456789")).toBe("12.345.678-9");
    expect(formatAuditValue("phone", "11987654321")).toBe("(11) 98765-4321");
    expect(formatAuditValue("birth_date", "1990-01-31")).toBe("31/01/1990");
    expect(formatAuditValue("sex", "F")).toBe("Feminino");
    expect(formatAuditValue("email", null)).toBe("—");
    expect(formatAuditValue("description", "  ")).toBe("—");
    expect(formatAuditValue("name", "Ana")).toBe("Ana");
  });

  it("lists changes in the detail page order, unknown fields last", () => {
    const changes = auditChanges({
      changes: {
        extra: { old: "a", new: "b" },
        phone: { old: "11900000000", new: "11911111111" },
        name: { old: "Ana", new: "Ana Lima" },
      },
    });
    expect(changes).toEqual([
      { field: "name", label: "Nome completo", old: "Ana", new: "Ana Lima" },
      {
        field: "phone",
        label: "Celular",
        old: "(11) 90000-0000",
        new: "(11) 91111-1111",
      },
      { field: "extra", label: "extra", old: "a", new: "b" },
    ]);
  });

  it("handles empty changes (PHP serializes {} as [])", () => {
    expect(auditChanges({ changes: [] })).toEqual([]);
  });
});

describe("audit formatting (Sprint 5: address and photo)", () => {
  it("labels address fields in form order and formats CEP/UF", () => {
    const changes = auditChanges({
      changes: {
        "address.city": { old: null, new: "São Paulo" },
        "address.cep": { old: null, new: "01310100" },
        "address.state": { old: "rj", new: "sp" },
        name: { old: "A", new: "B" },
      },
    });
    expect(changes.map((c) => c.label)).toEqual([
      "Nome completo",
      "Endereço: CEP",
      "Endereço: Cidade",
      "Endereço: UF",
    ]);
    expect(changes[1]).toMatchObject({ old: "—", new: "01310-100" });
    expect(changes[3]).toMatchObject({ old: "RJ", new: "SP" });
  });

  it("formats photo changes without exposing the stored value", () => {
    expect(formatAuditValue("photo", "photos/abc.jpg")).toBe("Com foto");
    expect(formatAuditValue("photo", true)).toBe("Com foto");
    expect(formatAuditValue("photo", false)).toBe("—");
    expect(formatAuditValue("photo", null)).toBe("—");
    expect(formatAuditValue("photo", "adicionada")).toBe("Adicionada");
    expect(formatAuditValue("photo", "Removida")).toBe("Removida");
    expect(
      auditChanges({ changes: { photo: { old: null, new: "v2" } } })
    ).toEqual([
      {
        field: "photo",
        label: "Foto",
        old: "—",
        new: "Com foto",
        summary: "Alterada",
      },
    ]);
    const summary = (value: string | null) =>
      auditChanges({ changes: { photo: { old: null, new: value } } })[0]
        .summary;
    expect(summary("adicionada")).toBe("Adicionada");
    expect(summary("alterada")).toBe("Alterada");
    expect(summary("removida")).toBe("Removida");
    expect(summary(null)).toBe("Removida");
    expect(summary("false")).toBe("Removida");
  });

  it("labels and describes the photo event", () => {
    expect(auditEventLabel("photo")).toBe("Foto");
    expect(auditEventIcon("photo")).toBe("b-icon-camera");
    expect(auditEventDescription("photo")).toBe("Foto alterada.");
    expect(auditEventDescription("photo", { changes: [] })).toBe(
      "Foto alterada."
    );
    expect(
      auditEventDescription("photo", {
        changes: { photo: { old: null, new: "abc" } },
      })
    ).toBe("Foto atualizada.");
    expect(
      auditEventDescription("photo", {
        changes: { photo: { old: null, new: "adicionada" } },
      })
    ).toBe("Foto adicionada.");
    expect(
      auditEventDescription("photo", {
        changes: { photo: { old: "abc", new: null } },
      })
    ).toBe("Foto removida.");
    expect(
      auditEventDescription("photo", {
        changes: { photo_path: { old: "abc", new: "" } },
      })
    ).toBe("Foto removida.");
    expect(auditEventDescription("updated", { changes: [] })).toBe(
      "Nenhum campo alterado."
    );
  });
});
