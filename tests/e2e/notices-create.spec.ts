import { test, expect } from '@playwright/test';

test('notices create page uses full width layout', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });

  // login
  await page.goto('http://localhost:8088/login', { waitUntil: 'domcontentloaded' });
  await page.getByLabel('이메일').fill('admin@jungjin.test');
  await page.locator('#password').fill('jungjin1234!');
  await page.getByRole('button', { name: '로그인' }).click();
  await expect(page).toHaveURL(/\/dashboard/);

  // go to notices create
  await page.goto('http://localhost:8088/notices/create', { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(/\/notices\/create/);

  // Basic visual sanity checks
  await expect(page.getByRole('heading', { name: '공지 작성' })).toBeVisible();

  const card = page.locator('.card').first();
  await expect(card).toBeVisible();

  // Ensure card is not constrained to narrow max-width (rough heuristic).
  const cardBox = await card.boundingBox();
  expect(cardBox?.width).toBeGreaterThan(900);

  await page.screenshot({ path: 'test-results/notices-create.png', fullPage: true });
});

