import { describe, expect, it } from "vitest";
import {
  brToIso,
  isFutureBrDate,
  isoToBr,
  isValidBrDate,
  maskCpf,
  maskDate,
  maskPhone,
  maskRg,
  normalizeRg,
  onlyDigits,
} from "../format";
import { isValidCpf, isValidEmail } from "../validation";

describe("masks", () => {
  it("strips non digits", () => {
    expect(onlyDigits("625.189.530-60")).toBe("62518953060");
    expect(onlyDigits(null)).toBe("");
  });

  it("masks CPF progressively", () => {
    expect(maskCpf("625")).toBe("625");
    expect(maskCpf("6251895")).toBe("625.189.5");
    expect(maskCpf("62518953060")).toBe("625.189.530-60");
    expect(maskCpf("6251895306099")).toBe("625.189.530-60");
  });

  it("masks RG keeping the X check digit", () => {
    expect(maskRg("356880199")).toBe("35.688.019-9");
    expect(maskRg("35688019x")).toBe("35.688.019-X");
    expect(normalizeRg("35.688.019-x")).toBe("35688019X");
    expect(maskRg("1234567890")).toBe("1234567890");
  });

  it("masks landline and mobile phones", () => {
    expect(maskPhone("11928535718")).toBe("(11) 92853-5718");
    expect(maskPhone("1132345678")).toBe("(11) 3234-5678");
    expect(maskPhone("11")).toBe("(11");
    expect(maskPhone("")).toBe("");
  });

  it("masks dates", () => {
    expect(maskDate("20071996")).toBe("20/07/1996");
    expect(maskDate("2007")).toBe("20/07");
  });
});

describe("dates", () => {
  it("converts without timezone shifts", () => {
    expect(isoToBr("1996-07-20")).toBe("20/07/1996");
    expect(isoToBr("1996-07-20T00:00:00Z")).toBe("20/07/1996");
    expect(brToIso("20/07/1996")).toBe("1996-07-20");
    expect(brToIso("20/07/19")).toBe("");
    expect(isoToBr(null)).toBe("");
  });

  it("validates calendar dates", () => {
    expect(isValidBrDate("29/02/2024")).toBe(true);
    expect(isValidBrDate("29/02/2023")).toBe(false);
    expect(isValidBrDate("31/04/2020")).toBe(false);
    expect(isValidBrDate("00/01/2020")).toBe(false);
  });

  it("detects future dates", () => {
    expect(isFutureBrDate("01/01/2999")).toBe(true);
    expect(isFutureBrDate("01/01/2000")).toBe(false);
  });
});

describe("validation", () => {
  it("validates CPF check digits", () => {
    expect(isValidCpf("529.982.247-25")).toBe(true);
    expect(isValidCpf("529.982.247-26")).toBe(false);
    expect(isValidCpf("111.111.111-11")).toBe(false);
  });

  it("validates e-mails", () => {
    expect(isValidEmail("a@b.com")).toBe(true);
    expect(isValidEmail("a@b")).toBe(false);
  });
});
