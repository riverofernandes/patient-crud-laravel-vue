import type { Page } from "@playwright/test";
import { expect, gotoListing, patientRows, rowFor, test } from "../support/fixtures";
import { uniqueTag } from "../support/data";

async function expectNoHorizontalScroll(page: Page): Promise<void> {
  const { scrollWidth, clientWidth } = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  expect(scrollWidth, "page must not scroll horizontally").toBeLessThanOrEqual(clientWidth);
}

test.use({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true });

test.describe("Mobile (375px)", () => {
  test("listagem sem rolagem horizontal da página", async ({ page, api }) => {
    // Long name to stress the layout; the table may scroll inside its own container.
    const patient = await api.createPatient({ name: `${uniqueTag()} ${"Sobrenome Comprido ".repeat(3).trim()}` });

    await gotoListing(page);
    await expect(patientRows(page).first()).toBeVisible();
    await expectNoHorizontalScroll(page);
    await expect(page.getByRole("link", { name: "Novo paciente" })).toBeInViewport();

    await gotoListing(page, patient.name);
    await expect(rowFor(page, patient.name)).toBeVisible();
    await expectNoHorizontalScroll(page);
  });
});
