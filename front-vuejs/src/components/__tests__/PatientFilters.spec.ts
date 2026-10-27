// @vitest-environment jsdom
import { afterEach, describe, expect, it } from "vitest";
import { mount, type Wrapper } from "@vue/test-utils";
import type Vue from "vue";
import PatientFilters from "../PatientFilters.vue";
import { flushPromises, makeLocalVue } from "@/test/helpers";

const localVue = makeLocalVue();
let wrapper: Wrapper<Vue>;

function mountFilters(value = { sex: "", ageMin: null, ageMax: null }) {
  wrapper = mount(PatientFilters as never, {
    localVue,
    propsData: { value },
    attachTo: document.body,
  });
}

async function typeAge(id: string, value: string) {
  const input = wrapper.find(id);
  await input.setValue(value);
  await input.trigger("change");
  await flushPromises();
}

afterEach(() => wrapper?.destroy());

describe("PatientFilters", () => {
  it("shows the applied values", () => {
    mountFilters({ sex: "F", ageMin: 0, ageMax: 60 } as never);
    expect(
      (wrapper.find("#filter-sex").element as HTMLSelectElement).value
    ).toBe("F");
    expect(
      (wrapper.find("#filter-age-min").element as HTMLInputElement).value
    ).toBe("0");
    expect(
      (wrapper.find("#filter-age-max").element as HTMLInputElement).value
    ).toBe("60");
  });

  it("emits change when sex changes", async () => {
    mountFilters();
    await wrapper.find("#filter-sex").setValue("M");
    await flushPromises();
    expect(wrapper.emitted("change")?.[0]).toEqual([
      { sex: "M", ageMin: null, ageMax: null },
    ]);
  });

  it("emits valid ages on change", async () => {
    mountFilters();
    await typeAge("#filter-age-min", "18");
    expect(wrapper.emitted("change")?.[0]).toEqual([
      { sex: "", ageMin: 18, ageMax: null },
    ]);
  });

  it("blocks min > max with an inline, linked error", async () => {
    mountFilters({ sex: "", ageMin: 50, ageMax: null } as never);
    await typeAge("#filter-age-max", "20");

    expect(wrapper.emitted("change")).toBeUndefined();
    const max = wrapper.find("#filter-age-max");
    expect(max.attributes("aria-invalid")).toBe("true");
    expect(max.attributes("aria-describedby")).toBe("filter-age-max-error");
    expect(wrapper.find("#filter-age-max-error").text()).toBe(
      "A idade máxima deve ser maior ou igual à mínima."
    );

    await typeAge("#filter-age-max", "60");
    expect(wrapper.find("#filter-age-max-error").exists()).toBe(false);
    expect(wrapper.emitted("change")?.[0]).toEqual([
      { sex: "", ageMin: 50, ageMax: 60 },
    ]);
  });

  it("rejects ages outside 0–150", async () => {
    mountFilters();
    await typeAge("#filter-age-min", "151");
    expect(wrapper.emitted("change")).toBeUndefined();
    expect(wrapper.find("#filter-age-min-error").text()).toBe(
      "Informe uma idade entre 0 e 150."
    );
  });

  it("Limpar filtros clears everything and keeps focus in the panel", async () => {
    mountFilters({ sex: "F", ageMin: 10, ageMax: null } as never);
    const clear = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Limpar filtros")
      .at(0);
    expect(clear.attributes("aria-disabled")).toBeUndefined();
    await clear.trigger("click");
    await flushPromises();
    expect(wrapper.emitted("change")?.[0]).toEqual([
      { sex: "", ageMin: null, ageMax: null },
    ]);
    expect(document.activeElement?.id).toBe("filter-sex");
  });

  it("Limpar filtros is inactive with nothing to clear", async () => {
    mountFilters();
    const clear = wrapper
      .findAll("button")
      .filter((b) => b.text() === "Limpar filtros")
      .at(0);
    expect(clear.attributes("aria-disabled")).toBe("true");
    await clear.trigger("click");
    expect(wrapper.emitted("change")).toBeUndefined();
  });
});
