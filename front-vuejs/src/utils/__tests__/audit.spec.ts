import { describe, expect, it } from "vitest";
import {
  auditChanges,
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
