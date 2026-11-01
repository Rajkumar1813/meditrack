import { test, expect } from '@playwright/test';

test.describe('MediTrack Frontend E2E Smoke Tests', () => {
  test('home page renders brand name, header, and categories', async ({ page }) => {
    await page.goto('/');

    // Check title contains MediTrack
    await expect(page).toHaveTitle(/MediTrack/);

    // Check logo exists
    const logo = page.locator('header img[data-brand-logo]');
    await expect(logo).toBeVisible();

    // Check category cards rendered
    const categoryContainer = page.locator('#category-chips-container');
    await expect(categoryContainer).toBeVisible();
  });

  test('theme toggle switches dark mode class on html element', async ({ page }) => {
    await page.goto('/');

    const themeToggle = page.locator('#theme-toggle');
    await expect(themeToggle).toBeVisible();

    // Click theme toggle
    await themeToggle.click();
    const isDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));

    // Click again to revert
    await themeToggle.click();
    const isDarkReverted = await page.evaluate(() => document.documentElement.classList.contains('dark'));

    expect(isDark).not.toBe(isDarkReverted);
  });

  test('language toggle switches locale and updates labels', async ({ page }) => {
    await page.goto('/');

    const langToggle = page.locator('#lang-toggle');
    await expect(langToggle).toBeVisible();

    // Click language toggle to switch to Hindi
    await langToggle.click();
    const localeHi = await page.evaluate(() => document.documentElement.lang);
    expect(localeHi).toBe('hi');

    // Click again to switch back to English
    await langToggle.click();
    const localeEn = await page.evaluate(() => document.documentElement.lang);
    expect(localeEn).toBe('en');
  });

  test('login page displays OTP request form with 3D card layout', async ({ page }) => {
    await page.goto('/login.html');

    const identifierInput = page.locator('#login-identifier');
    await expect(identifierInput).toBeVisible();

    const sendOtpBtn = page.locator('#send-otp-btn');
    await expect(sendOtpBtn).toBeVisible();
  });

  test('admin page redirects unauthenticated users to login', async ({ page }) => {
    await page.goto('/admin/index.html');
    // Since guard checks auth and finds no token, should redirect to login.html
    await expect(page).toHaveURL(/login\.html/);
  });
});

