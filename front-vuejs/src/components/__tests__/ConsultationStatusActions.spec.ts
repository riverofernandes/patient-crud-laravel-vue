// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import ConsultationStatusActions from "../ConsultationStatusActions.vue";
import StatusBadge from "../StatusBadge.vue";
import { consultationService } from "@/services/consultationService";
import { notify } from "@/utils/notify";
import { flushPromises, makeLocalVue } from "@/test/helpers";
import { makeConsultation, validationError } from "@/test/fixtures";

vi.mock("@/services/consultationService", () => ({
  consultationService: { changeStatus: vi.fn() },
}));
vi.mock("@/utils/notify", () => ({ notify: vi.fn() }));

const changeStatus = vi.mocked(consultationService.changeStatus);
const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

function mountActions(status: string, propsData: object = {}) {
  wrapper = mount(ConsultationStatusActions as never, {
    localVue,
    propsData: {
      consultation: makeConsultation({ status: status as never }),
      ...propsData,
    },
    attachTo: document.body,
  });
}

const labels = () =>
  wrapper.findAll(".status-actions__btn").wrappers.map((b) => b.text());

beforeEach(() => {
  changeStatus.mockReset();
  vi.mocked(notify).mockReset();
});
afterEach(() => wrapper?.destroy());

describe("ConsultationStatusActions", () => {
  it.each([
    [
      "scheduled",
      [
        "Confirmar",
        "Marcar como realizado",
        "Registrar falta",
        "Cancelar atendimento",
      ],
    ],
    [
      "confirmed",
      ["Marcar como realizado", "Registrar falta", "Cancelar atendimento"],
    ],
    ["completed", []],
    ["no_show", []],
    ["cancelled", []],
  ])("%s shows only the allowed transitions", (status, expected) => {
    mountActions(status);
    expect(labels()).toEqual(expected);
    expect(wrapper.find("[role='group']").exists()).toBe(expected.length > 0);
  });

  it("confirms directly, toasts, emits and moves focus", async () => {
    const heading = document.createElement("h1");
    document.body.appendChild(heading);
    changeStatus.mockResolvedValue(makeConsultation({ status: "confirmed" }));
    mountActions("scheduled", { focusTarget: "h1" });
    await wrapper.find("[data-action='confirmed']").trigger("click");
    await flushPromises();
    expect(changeStatus).toHaveBeenCalledWith(11, "confirmed");
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      "Atendimento confirmado."
    );
    expect(wrapper.emitted("changed")?.[0][0]).toMatchObject({
      status: "confirmed",
    });
    await wrapper.vm.$nextTick();
    expect(document.activeElement).toBe(heading);
    heading.remove();
  });

  it("asks before cancelling; Voltar does nothing", async () => {
    mountActions("scheduled");
    await wrapper.find("[data-action='cancelled']").trigger("click");
    await flushPromises();
    const modal = document.querySelector(".modal") as HTMLElement;
    expect(modal.textContent).toContain("Deseja realmente cancelar");
    const back = Array.from(modal.querySelectorAll("button")).find(
      (b) => b.textContent?.trim() === "Voltar"
    );
    back?.click();
    await flushPromises();
    expect(changeStatus).not.toHaveBeenCalled();
  });

  it("registers a no-show after confirmation", async () => {
    changeStatus.mockResolvedValue(makeConsultation({ status: "no_show" }));
    mountActions("confirmed");
    await wrapper.find("[data-action='no_show']").trigger("click");
    await flushPromises();
    const confirm = document.querySelector(
      ".status-actions__confirm"
    ) as HTMLButtonElement;
    expect(confirm.textContent?.trim()).toBe("Registrar falta");
    confirm.click();
    await flushPromises();
    expect(changeStatus).toHaveBeenCalledWith(11, "no_show");
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "success",
      "Falta registrada."
    );
    expect(wrapper.emitted("changed")).toBeTruthy();
  });

  it("shows the API message on an invalid transition and asks for a reload", async () => {
    changeStatus.mockRejectedValue(
      validationError({ status: ["Transição inválida."] })
    );
    mountActions("scheduled");
    await wrapper.find("[data-action='completed']").trigger("click");
    await flushPromises();
    expect(notify).toHaveBeenCalledWith(
      expect.anything(),
      "error",
      "Transição inválida."
    );
    expect(wrapper.emitted("stale")).toBeTruthy();
    expect(wrapper.emitted("changed")).toBeFalsy();
  });

  it("names buttons with context in lists", () => {
    mountActions("scheduled", { context: "de Ana às 14:30" });
    expect(
      wrapper.find("[data-action='confirmed']").attributes("aria-label")
    ).toBe("Confirmar: atendimento de Ana às 14:30");
  });
});

describe("StatusBadge", () => {
  it("shows text, icon and an optional hidden prefix", () => {
    const badge = mount(StatusBadge as never, {
      localVue,
      propsData: { status: "no_show", srPrefix: "Status:" },
    });
    expect(badge.text()).toContain("Faltou");
    expect(badge.find(".sr-only").text()).toBe("Status:");
    expect(badge.classes()).toContain("status-badge--no_show");
    expect(badge.find("svg").attributes("aria-hidden")).toBe("true");
  });
});
