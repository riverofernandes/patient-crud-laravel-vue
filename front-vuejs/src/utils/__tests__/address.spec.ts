import { describe, expect, it } from "vitest";
import {
  UFS,
  addressErrorField,
  formatAddress,
  isAddressEmpty,
  maskCep,
  toAddressForm,
  toAddressPayload,
  validateAddress,
} from "../address";

const full = {
  cep: "01310100",
  street: "Avenida Paulista",
  number: "1578",
  complement: "Sala 12",
  neighborhood: "Bela Vista",
  city: "São Paulo",
  state: "SP",
};

describe("address helpers", () => {
  it("lists the 27 UFs", () => {
    expect(UFS).toHaveLength(27);
    expect(new Set(UFS.map((u) => u.value)).size).toBe(27);
  });

  it("masks the CEP progressively", () => {
    expect(maskCep("01310")).toBe("01310");
    expect(maskCep("013101")).toBe("01310-1");
    expect(maskCep("01310-100999")).toBe("01310-100");
    expect(maskCep(null)).toBe("");
  });

  it("builds form state from the API (and an empty one)", () => {
    expect(toAddressForm({ ...full, state: "sp" })).toEqual({
      ...full,
      cep: "01310-100",
    });
    const empty = toAddressForm(null);
    expect(isAddressEmpty(empty)).toBe(true);
    expect(isAddressEmpty({ ...empty, complement: " x " })).toBe(false);
  });

  it("requires nothing when empty, everything but the complement otherwise", () => {
    expect(validateAddress(toAddressForm(null))).toEqual({
      errors: {},
      missingRequired: false,
    });
    const result = validateAddress({ ...toAddressForm(null), complement: "A" });
    expect(result.missingRequired).toBe(true);
    expect(Object.keys(result.errors).sort()).toEqual(
      ["cep", "city", "neighborhood", "number", "state", "street"].sort()
    );
    expect(result.errors.complement).toBeUndefined();
  });

  it("checks the CEP length and the UF", () => {
    const form = { ...toAddressForm(full), cep: "01310-10", state: "XX" };
    expect(validateAddress(form)).toEqual({
      errors: { cep: "CEP inválido", state: "UF inválida" },
      missingRequired: false,
    });
    expect(validateAddress(toAddressForm(full)).errors).toEqual({});
  });

  it("builds the payload: digits-only CEP, null complement, null when empty", () => {
    expect(
      toAddressPayload({ ...toAddressForm(full), complement: "  " })
    ).toEqual({ ...full, complement: null });
    expect(toAddressPayload(toAddressForm(null))).toBeNull();
  });

  it("formats one line like the CSV export", () => {
    expect(formatAddress(full)).toBe(
      "Avenida Paulista, 1578 - Sala 12 - Bela Vista · São Paulo/SP · CEP 01310-100"
    );
    expect(formatAddress({ ...full, complement: null })).toBe(
      "Avenida Paulista, 1578 - Bela Vista · São Paulo/SP · CEP 01310-100"
    );
    expect(formatAddress(null)).toBe("");
  });

  it("maps 422 keys to address fields", () => {
    expect(addressErrorField("address.cep")).toBe("cep");
    expect(addressErrorField("address.state")).toBe("state");
    expect(addressErrorField("address")).toBe("cep");
    expect(addressErrorField("address.unknown")).toBeNull();
    expect(addressErrorField("name")).toBeNull();
  });
});
