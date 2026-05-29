import { test, expect } from '@playwright/test';

test('admin can login', async ({ page }) => {
  await page.goto('http://localhost:8088/login', { waitUntil: 'domcontentloaded' });

  await page.getByLabel('이메일').fill('admin@jungjin.test');
  await page.locator('#password').fill('jungjin1234!');
  await page.getByRole('button', { name: '로그인' }).click();

  await expect(page).toHaveURL(/\/dashboard/);
});

