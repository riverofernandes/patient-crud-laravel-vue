import { describe, expect, it } from "vitest";
import {
  acuityDenominator,
  axisEnabled,
  eyeExamFieldId,
  eyeRefraction,
  formatDiopter,
  hasRefraction,
  isQuarter,
  mapEyeExamErrors,
  normalizeDiopterText,
  parseDiopter,
  refractionSummary,
  stepDiopter,
  toEyeExamForm,
  toEyeExamPayload,
  toNumber,
  validateEyeExamForm,
} from "../eyeExam";
import type { EyeExam } from "@/services/consultationService";

const exam: EyeExam = {
  od: {
    visual_acuity_sc: "20/40",
    visual_acuity_cc: "20/20",
    spherical: -1.25,
    cylindrical: -0.5,
    axis: 180,
    addition: null,
    iop: 14,
  },
  oe: {
    visual_acuity_sc: "20/50",
    visual_acuity_cc: null,
    spherical: "-1.00" as unknown as number,
    cylindrical: 0,
    axis: null,
    addition: 2,
    iop: null,
  },
  diagnosis: "Miopia leve.",
  observations: null,
};

describe("diopters", () => {
  it("formats with explicit sign and typographic minus", () => {
    expect(formatDiopter(1.25)).toBe("+1.25");
    expect(formatDiopter(-0.5)).toBe("−0.50");
    expect(formatDiopter(0)).toBe("0.00");
    expect(formatDiopter("-2")).toBe("−2.00");
    expect(formatDiopter(null)).toBe("");
    expect(formatDiopter("x")).toBe("");
  });

  it("parses comma/dot, ASCII and typographic minus", () => {
    expect(parseDiopter("-1,25")).toBe(-1.25);
    expect(parseDiopter("−0.50")).toBe(-0.5);
    expect(parseDiopter("+2")).toBe(2);
    expect(parseDiopter(" .75 ")).toBe(0.75);
    expect(parseDiopter("")).toBeNull();
    expect(parseDiopter("1.2.3")).toBeNaN();
    expect(parseDiopter("abc")).toBeNaN();
    expect(toNumber("1.5")).toBe(1.5);
    expect(toNumber("")).toBeNull();
    expect(toNumber("x")).toBeNull();
  });

  it("normalizes typed text on blur", () => {
    expect(normalizeDiopterText("-1,5")).toBe("−1.50");
    expect(normalizeDiopterText("2")).toBe("+2.00");
    expect(normalizeDiopterText(" abc ")).toBe("abc");
    expect(normalizeDiopterText("")).toBe("");
  });

  it("steps by 0.25 without float drift, clamped to the range", () => {
    expect(stepDiopter("", 1, "spherical")).toBe("+0.25");
    expect(stepDiopter("", -1, "spherical")).toBe("−0.25");
    expect(stepDiopter("+0.10", 1, "spherical")).toBe("+0.25");
    let v = "0.00";
    for (let i = 0; i < 7; i++) v = stepDiopter(v, -1, "spherical");
    expect(v).toBe("−1.75");
    expect(stepDiopter("+30.00", 1, "spherical")).toBe("+30.00");
    expect(stepDiopter("", 1, "cylindrical")).toBe("0.00");
    expect(stepDiopter("0.00", -1, "cylindrical")).toBe("−0.25");
    expect(stepDiopter("−10.00", -1, "cylindrical")).toBe("−10.00");
    expect(stepDiopter("", 1, "addition")).toBe("+0.75");
    expect(stepDiopter("", -1, "addition")).toBe("+0.75");
    expect(stepDiopter("+0.75", -1, "addition")).toBe("+0.75");
    expect(stepDiopter("+4.00", 1, "addition")).toBe("+4.00");
    expect(stepDiopter("junk", 1, "spherical")).toBe("+0.25");
    expect(isQuarter(1.75)).toBe(true);
    expect(isQuarter(1.3)).toBe(false);
  });
});

describe("eye exam form", () => {
  it("axis is enabled only when the cylinder is not zero", () => {
    expect(axisEnabled({ cylindrical: "" })).toBe(false);
    expect(axisEnabled({ cylindrical: "0.00" })).toBe(false);
    expect(axisEnabled({ cylindrical: "abc" })).toBe(false);
    expect(axisEnabled({ cylindrical: "-0.25" })).toBe(true);
  });

  it("acuity keeps only N of 20/N", () => {
    expect(acuityDenominator("20/40")).toBe("40");
    expect(acuityDenominator("20 / 200")).toBe("200");
    expect(acuityDenominator("4a0")).toBe("40");
    expect(acuityDenominator(null)).toBe("");
  });

  it("round-trips API values through the form", () => {
    const form = toEyeExamForm(exam);
    expect(form.od).toMatchObject({
      visual_acuity_sc: "40",
      spherical: "−1.25",
      cylindrical: "−0.50",
      axis: "180",
      iop: "14",
      addition: "",
    });
    expect(form.oe.spherical).toBe("−1.00");
    expect(form.oe.addition).toBe("+2.00");
    expect(toEyeExamPayload(form)).toEqual({
      od: { ...exam.od },
      oe: { ...exam.oe, spherical: -1 },
      diagnosis: "Miopia leve.",
      observations: null,
    });
    expect(toEyeExamForm(null).od.spherical).toBe("");
  });

  it("sends axis null when the cylinder is 0 even if typed", () => {
    const form = toEyeExamForm(null);
    form.od.cylindrical = "0.00";
    form.od.axis = "90";
    expect(toEyeExamPayload(form).od.axis).toBeNull();
  });

  it("validates like the API", () => {
    const form = toEyeExamForm(null);
    form.od.visual_acuity_sc = "5";
    form.od.spherical = "+31";
    form.od.cylindrical = "-0.30";
    form.oe.cylindrical = "+0.50";
    form.oe.addition = "0.50";
    form.oe.iop = "61";
    form.oe.spherical = "x";
    form.oe.visual_acuity_cc = "400";
    const errors = validateEyeExamForm(form);
    expect(errors).toEqual({
      "od.visual_acuity_sc": "Use 20/N, com N entre 10 e 400",
      "od.spherical": "Entre −30.00 e +30.00",
      "od.cylindrical": "Use passos de 0.25",
      "od.axis": "Informe o eixo",
      "oe.spherical": "Valor inválido",
      "oe.cylindrical": "Entre −10.00 e 0.00",
      "oe.axis": "Informe o eixo",
      "oe.addition": "Entre +0.75 e +4.00",
      "oe.iop": "Inteiro entre 5 e 60 mmHg",
    });
    form.od.axis = "181";
    expect(validateEyeExamForm(form)["od.axis"]).toBe("Inteiro entre 0 e 180");
    // Leading zero is rejected, as in the API.
    form.od.visual_acuity_sc = "040";
    expect(validateEyeExamForm(form)["od.visual_acuity_sc"]).toBe(
      "Use 20/N, com N entre 10 e 400"
    );
    const long = toEyeExamForm(null);
    long.diagnosis = "a".repeat(2001);
    long.observations = "a".repeat(5001);
    expect(Object.keys(validateEyeExamForm(long))).toEqual([
      "diagnosis",
      "observations",
    ]);
    expect(validateEyeExamForm(toEyeExamForm(exam))).toEqual({});
  });

  it("maps API 422 keys and field ids", () => {
    expect(
      mapEyeExamErrors({
        "od.axis": ["Obrigatório"],
        "oe[iop]": ["Inválido"],
        status: ["Só realizados"],
        empty: [],
      })
    ).toEqual({
      "od.axis": "Obrigatório",
      "oe.iop": "Inválido",
      status: "Só realizados",
    });
    expect(mapEyeExamErrors(null)).toEqual({});
    expect(eyeExamFieldId("od.visual_acuity_sc")).toBe(
      "ee-od-visual-acuity-sc"
    );
  });

  it("summarizes refraction", () => {
    expect(refractionSummary(exam)).toBe(
      "OD −1.25 −0.50 × 180° · OE −1.00 Ad +2.00"
    );
    expect(eyeRefraction({ cylindrical: -1, axis: 90 })).toBe(
      "0.00 −1.00 × 90°"
    );
    expect(eyeRefraction(null)).toBe("");
    expect(refractionSummary(null)).toBe("");
    expect(hasRefraction(exam)).toBe(true);
    expect(hasRefraction(null)).toBe(false);
    expect(
      hasRefraction({
        ...exam,
        od: { ...exam.od, spherical: null, cylindrical: null },
        oe: { ...exam.oe, spherical: null, cylindrical: null, addition: null },
      })
    ).toBe(false);
  });
});
