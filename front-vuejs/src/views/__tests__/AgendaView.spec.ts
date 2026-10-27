// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import type VueRouter from "vue-router";
import AgendaView, { PROFESSIONAL_DEBOUNCE } from "../AgendaView.vue";
import { consultationService } from "@/services/consultationService";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";
import { makeConsultation } from "@/test/fixtures";

vi.mock("@/services/consultationService", () => ({
  consultationService: { agenda: vi.fn(), changeStatus: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const agenda = vi.mocked(consultationService.agenda);
const changeStatus = vi.mocked(consultationService.changeStatus);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;
let router: VueRouter;

// Thursday 2026-10-29 10:00 in São Paulo (13:00 UTC).
const NOW = new Date("2026-10-29T13:00:00Z");

async function mountAgenda(path = "/agenda") {
  router = makeRouter();
  await router.push(path).catch(() => undefined);
  wrapper = mount(AgendaView as never, {
    localVue,
    router,
    attachTo: document.body,
  });
  await flushPromises();
}

const lastRange = () => {
  const call = agenda.mock.calls[agenda.mock.calls.length - 1][0];
  return [call.date_from, call.date_to];
};
const button = (name: string) =>
  wrapper
    .findAll("button")
    .filter((b) => (b.attributes("aria-label") ?? b.text()) === name)
    .at(0);

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(NOW);
  agenda.mockReset();
  agenda.mockResolvedValue([]);
  changeStatus.mockReset();
});
afterEach(() => {
  wrapper?.destroy();
  vi.useRealTimers();
});

describe("AgendaView", () => {
  it("defaults to the current São Paulo week (Mon–Sun), today highlighted, empty days shown", async () => {
    agenda.mockResolvedValue([
      makeConsultation({ id: 1, scheduled_at: "2026-10-29T09:00:00-03:00" }),
      makeConsultation({
        id: 2,
        scheduled_at: "2026-10-29T08:00:00-03:00",
        status: "completed",
      }),
    ]);
    await mountAgenda();
    expect(lastRange()).toEqual(["2026-10-26", "2026-11-01"]);
    expect(wrapper.text()).toContain("Semana de 26/10 a 01/11/2026");
    const days = wrapper.findAll(".agenda-day");
    expect(days).toHaveLength(7);
    expect(days.at(0).text()).toContain("Segunda-feira, 26/10/2026");
    expect(days.at(0).text()).toContain("Nenhum atendimento");
    expect(days.at(3).classes()).toContain("is-today");
    expect(days.at(3).text()).toContain("Hoje");
    const items = days.at(3).findAll(".agenda-item");
    expect(items.at(0).text()).toContain("08:00–08:30");
    expect(items.at(1).text()).toContain("09:00–09:30");
    expect(items.at(1).find(".agenda-item__patient").attributes("href")).toBe(
      "/pacientes/7"
    );
    // Completed: no quick actions; scheduled: transitions available.
    expect(items.at(0).findAll(".status-actions__btn")).toHaveLength(0);
    expect(items.at(1).findAll(".status-actions__btn")).toHaveLength(4);
    expect(wrapper.find("[role='status']").text()).toContain("2 atendimentos");
  });

  it("navigates weeks through ?semana and back to today", async () => {
    await mountAgenda();
    await button("Próxima semana").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query.semana).toBe("2026-11-02");
    expect(lastRange()).toEqual(["2026-11-02", "2026-11-08"]);
    await button("Semana anterior").trigger("click");
    await button("Semana anterior").trigger("click");
    await flushPromises();
    // Back on the current week: the param is dropped (default).
    expect(router.currentRoute.query.semana).toBe("2026-10-19");
    await button("Hoje").trigger("click");
    await flushPromises();
    expect(router.currentRoute.query.semana).toBeUndefined();
    expect(lastRange()).toEqual(["2026-10-26", "2026-11-01"]);
  });

  it("normalizes ?semana to the Monday (and drops invalid dates)", async () => {
    await mountAgenda("/agenda?semana=2026-12-31");
    expect(router.currentRoute.query.semana).toBe("2026-12-28");
    expect(lastRange()).toEqual(["2026-12-28", "2027-01-03"]);
    wrapper.destroy();
    await mountAgenda("/agenda?semana=2026-02-30");
    expect(router.currentRoute.query.semana).toBeUndefined();
    expect(lastRange()).toEqual(["2026-10-26", "2026-11-01"]);
  });

  it("filters by status (multi) and professional (debounced), synced to the URL", async () => {
    await mountAgenda("/agenda?status=confirmed&profissional=Ana");
    expect(agenda).toHaveBeenLastCalledWith({
      date_from: "2026-10-26",
      date_to: "2026-11-01",
      status: ["confirmed"],
      professional: "Ana",
    });
    expect(
      (wrapper.find("#agenda-professional").element as HTMLInputElement).value
    ).toBe("Ana");
    const box = (label: string) =>
      wrapper
        .findAll(".status-chip")
        .filter((c) => c.text() === label)
        .at(0)
        .find("input");
    expect((box("Confirmado").element as HTMLInputElement).checked).toBe(true);
    await box("Agendado").trigger("change");
    await flushPromises();
    expect(router.currentRoute.query.status).toBe("scheduled,confirmed");
    expect(agenda.mock.calls[agenda.mock.calls.length - 1][0].status).toEqual([
      "scheduled",
      "confirmed",
    ]);

    agenda.mockClear();
    await wrapper.find("#agenda-professional").setValue("Hel");
    vi.advanceTimersByTime(PROFESSIONAL_DEBOUNCE - 50);
    await flushPromises();
    expect(agenda).not.toHaveBeenCalled();
    vi.advanceTimersByTime(60);
    await flushPromises();
    expect(router.currentRoute.query.profissional).toBe("Hel");
    expect(agenda.mock.calls[0][0].professional).toBe("Hel");
  });

  it("quick status action updates the item in place", async () => {
    agenda.mockResolvedValue([
      makeConsultation({ id: 3, scheduled_at: "2026-10-29T09:00:00-03:00" }),
    ]);
    changeStatus.mockResolvedValue(
      makeConsultation({
        id: 3,
        scheduled_at: "2026-10-29T09:00:00-03:00",
        status: "confirmed",
      })
    );
    await mountAgenda();
    await wrapper.find("[data-action='confirmed']").trigger("click");
    await flushPromises();
    expect(wrapper.find(".agenda-item").text()).toContain("Confirmado");
    expect(wrapper.findAll(".agenda-item .status-actions__btn")).toHaveLength(
      3
    );
    expect(agenda).toHaveBeenCalledTimes(1);
  });

  it("shows an error with retry", async () => {
    agenda.mockRejectedValueOnce(new Error("x"));
    await mountAgenda();
    expect(wrapper.find("[role='alert']").text()).toContain(
      "Não foi possível carregar a agenda"
    );
    await wrapper.find("[role='alert'] button").trigger("click");
    await flushPromises();
    expect(wrapper.find("[role='alert']").exists()).toBe(false);
  });
});
