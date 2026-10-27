import { describe, expect, it } from "vitest";
import {
  allowedActions,
  canTransition,
  isFinalStatus,
  STATUS_META,
  statusIcon,
  statusLabel,
} from "../consultationStatus";

describe("consultation status", () => {
  it("has the pt-BR labels", () => {
    expect(statusLabel("scheduled")).toBe("Agendado");
    expect(statusLabel("confirmed")).toBe("Confirmado");
    expect(statusLabel("completed")).toBe("Realizado");
    expect(statusLabel("no_show")).toBe("Faltou");
    expect(statusLabel("cancelled")).toBe("Cancelado");
    expect(statusLabel("weird")).toBe("weird");
    expect(statusIcon("weird")).toBe("b-icon-circle");
    Object.values(STATUS_META).forEach((m) =>
      expect(m.icon).toMatch(/^b-icon-/)
    );
  });

  it("follows the API transition table", () => {
    expect(canTransition("scheduled", "confirmed")).toBe(true);
    expect(canTransition("scheduled", "completed")).toBe(true);
    expect(canTransition("confirmed", "confirmed")).toBe(false);
    expect(canTransition("confirmed", "cancelled")).toBe(true);
    expect(canTransition("completed", "cancelled")).toBe(false);
    expect(canTransition("cancelled", "scheduled")).toBe(false);
    expect(canTransition("x", "confirmed")).toBe(false);
    expect(isFinalStatus("completed")).toBe(true);
    expect(isFinalStatus("no_show")).toBe(true);
    expect(isFinalStatus("cancelled")).toBe(true);
    expect(isFinalStatus("scheduled")).toBe(false);
    expect(isFinalStatus("x")).toBe(false);
  });

  it("derives the action buttons from the transitions", () => {
    expect(allowedActions("scheduled").map((a) => a.label)).toEqual([
      "Confirmar",
      "Marcar como realizado",
      "Registrar falta",
      "Cancelar atendimento",
    ]);
    expect(allowedActions("confirmed").map((a) => a.target)).toEqual([
      "completed",
      "no_show",
      "cancelled",
    ]);
    expect(allowedActions("completed")).toEqual([]);
    expect(allowedActions("nope")).toEqual([]);
    const byTarget = Object.fromEntries(
      allowedActions("scheduled").map((a) => [a.target, a])
    );
    expect(byTarget.cancelled.confirm).toBeTruthy();
    expect(byTarget.no_show.confirm).toBeTruthy();
    expect(byTarget.confirmed.confirm).toBeUndefined();
    expect(byTarget.completed.confirm).toBeUndefined();
  });
});
