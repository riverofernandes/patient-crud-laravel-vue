// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientConsultations from "../PatientConsultations.vue";
import PatientTimeline from "../PatientTimeline.vue";
import {
  consultationService,
  type TimelineItem,
} from "@/services/consultationService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue, makeRouter } from "@/test/helpers";
import { exam, makeConsultation } from "@/test/fixtures";

vi.mock("@/services/consultationService", () => ({
  consultationService: { listByPatient: vi.fn(), timeline: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const list = vi.mocked(consultationService.listByPatient);
const timeline = vi.mocked(consultationService.timeline);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

const page = <T>(data: T[], current: number, last: number, total: number) => ({
  data,
  meta: {
    current_page: current,
    last_page: last,
    per_page: 10,
    total,
    from: 1,
    to: data.length,
  },
});

function mountIt(component: unknown) {
  wrapper = mount(component as never, {
    localVue,
    router: makeRouter(),
    propsData: { patientId: "7" },
    attachTo: document.body,
  });
}

beforeEach(() => {
  list.mockReset();
  timeline.mockReset();
  vi.mocked(notify).mockReset();
});
afterEach(() => {
  wrapper?.destroy();
  vi.useRealTimers();
});

describe("PatientConsultations", () => {
  it("lists consultations with status, excerpt and exam indicator, then loads more", async () => {
    list.mockResolvedValueOnce(
      page(
        [
          makeConsultation({
            id: 2,
            status: "completed",
            eye_exam: exam,
            reason: "a ".repeat(100),
          }),
          makeConsultation({ id: 1, professional_crm: null, reason: null }),
        ],
        1,
        2,
        3
      )
    );
    mountIt(PatientConsultations);
    await flushPromises();
    expect(list).toHaveBeenCalledWith("7", { page: 1, per_page: 10 });
    const items = wrapper.findAll(".consultation-item");
    expect(items).toHaveLength(2);
    expect(items.at(0).text()).toContain("03/11/2026");
    expect(items.at(0).text()).toContain("14:30–15:00");
    expect(items.at(0).text()).toContain("Realizado");
    expect(items.at(0).text()).toContain("Prontuário registrado");
    expect(
      items.at(0).find(".consultation-item__reason").text().endsWith("…")
    ).toBe(true);
    expect(items.at(1).text()).not.toContain("Prontuário registrado");
    expect(items.at(0).find("a").attributes("href")).toBe("/atendimentos/2");
    expect(wrapper.find("a.btn-primary").text()).toBe("Novo atendimento");
    expect(wrapper.text()).toContain("Mostrando 2 de 3 atendimentos");

    list.mockResolvedValueOnce(
      page([makeConsultation({ id: 1 }), makeConsultation({ id: 0 })], 2, 2, 3)
    );
    const more = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Carregar mais");
    await more.at(0).trigger("click");
    await flushPromises();
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll(".consultation-item")).toHaveLength(3);
    expect(
      wrapper.findAll("button").filter((b) => b.text() === "Carregar mais")
    ).toHaveLength(0);
    expect(document.activeElement?.getAttribute("data-consultation-id")).toBe(
      "0"
    );
  });

  it("shows empty and error states", async () => {
    list.mockResolvedValueOnce(page([], 1, 1, 0));
    mountIt(PatientConsultations);
    await flushPromises();
    expect(wrapper.text()).toContain("Nenhum atendimento registrado");
    wrapper.destroy();

    list.mockRejectedValueOnce(new Error("x"));
    mountIt(PatientConsultations);
    await flushPromises();
    expect(wrapper.find("[role='alert']").text()).toContain(
      "Não foi possível carregar os atendimentos"
    );
  });

  it("toasts when loading more fails", async () => {
    list.mockResolvedValueOnce(page([makeConsultation()], 1, 2, 2));
    mountIt(PatientConsultations);
    await flushPromises();
    list.mockRejectedValueOnce(new Error("x"));
    await wrapper
      .findAll("button")
      .filter((b) => b.text() === "Carregar mais")
      .at(0)
      .trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Não foi possível carregar mais atendimentos."
    );
  });
});

describe("PatientTimeline", () => {
  const consultationItem = (id: number, at: string, extra = {}) => ({
    type: "consultation" as const,
    occurred_at: at,
    consultation: makeConsultation({ id, scheduled_at: at, ...extra }),
  });
  const auditItem = (
    id: number,
    at: string,
    event = "updated"
  ): TimelineItem => ({
    type: "audit" as const,
    occurred_at: at,
    audit: {
      id,
      event: event as never,
      changes:
        event === "updated"
          ? { phone: { old: "11900000000", new: "11911111111" } }
          : [],
      created_at: at,
    },
  });

  it("groups by day, renders consultations (with refraction) and audits, loads more into the same day", async () => {
    // Pin "today" so neither fixture day gets the "Hoje, " prefix.
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date("2026-12-01T12:00:00-03:00"));
    timeline.mockResolvedValueOnce(
      page(
        [
          consultationItem(5, "2026-11-03T14:30:00-03:00", {
            status: "completed",
            eye_exam: exam,
          }),
          auditItem(5, "2026-11-03T10:00:00-03:00"),
          consultationItem(4, "2026-10-27T09:00:00-03:00", {
            status: "cancelled",
          }),
        ],
        1,
        2,
        4
      )
    );
    mountIt(PatientTimeline);
    await flushPromises();
    expect(timeline).toHaveBeenCalledWith("7", { page: 1, per_page: 15 });
    expect(
      wrapper.find("ol[aria-label='Linha do tempo do paciente']").exists()
    ).toBe(true);
    const headings = () =>
      wrapper.findAll(".timeline-day__title").wrappers.map((h) => h.text());
    expect(headings()).toEqual([
      "Terça-feira, 03/11/2026",
      "Terça-feira, 27/10/2026",
    ]);
    const first = wrapper.findAll(".timeline-item").at(0);
    expect(first.text()).toContain("Atendimento");
    expect(first.text()).toContain("Realizado");
    expect(first.find(".timeline-item__refraction").text()).toContain(
      "OD −1.25 −0.50 × 180°"
    );
    const audit = wrapper.findAll(".timeline-item").at(1);
    expect(audit.text()).toContain("Edição");
    expect(
      audit.find(".timeline-change").text().replace(/\s+/g, " ")
    ).toContain("Celular: de (11) 90000-0000 para (11) 91111-1111");

    timeline.mockResolvedValueOnce(
      page([auditItem(1, "2026-10-27T08:00:00-03:00", "created")], 2, 2, 4)
    );
    await wrapper
      .findAll("button")
      .filter((b) => b.text() === "Carregar mais")
      .at(0)
      .trigger("click");
    await flushPromises();
    expect(headings()).toHaveLength(2);
    expect(wrapper.text()).toContain("Paciente cadastrado.");
    expect(wrapper.text()).toContain("Mostrando 4 de 4 registros");
  });

  it("shows empty and error states", async () => {
    timeline.mockResolvedValueOnce(page([], 1, 1, 0));
    mountIt(PatientTimeline);
    await flushPromises();
    expect(wrapper.text()).toContain("Nada registrado ainda");
    wrapper.destroy();
    timeline.mockRejectedValueOnce(new Error("x"));
    mountIt(PatientTimeline);
    await flushPromises();
    expect(wrapper.find("[role='alert']").exists()).toBe(true);
  });
});
