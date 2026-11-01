import { test, expect } from '@playwright/test';

test.describe('Login & OTP Flow E2E Tests', () => {
  test('mode toggle switches between mobile number and email', async ({ page }) => {
    await page.goto('/login.html');

    const phoneTab = page.locator('#tab-phone');
    const emailTab = page.locator('#tab-email');
    const prefix = page.locator('#phone-prefix');
    const input = page.locator('#login-identifier');

    // Default mode is phone
    await expect(prefix).toBeVisible();
    await expect(input).toHaveAttribute('type', 'tel');

    // Click Email Tab
    await emailTab.click();
    await expect(prefix).toBeHidden();
    await expect(input).toHaveAttribute('type', 'email');
    await expect(input).toHaveAttribute('placeholder', 'name@example.com');

    // Click back to Phone Tab
    await phoneTab.click();
    await expect(prefix).toBeVisible();
    await expect(input).toHaveAttribute('type', 'tel');
  });

  test('validates invalid mobile number with error message', async ({ page }) => {
    await page.goto('/login.html');

    const input = page.locator('#login-identifier');
    const sendBtn = page.locator('#send-otp-btn');
    const errorMsg = page.locator('#login-error-message');

    // Enter short invalid number
    await input.fill('123');
    await sendBtn.click();

    await expect(errorMsg).toBeVisible();
    await expect(errorMsg).toContainText('Please enter a valid 10-digit mobile number');
  });

  test('complete OTP request and admin login redirect flow', async ({ page }) => {
    // Intercept backend OTP request API
    await page.route('**/api/v1/auth/otp/request', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Verification code sent.',
          data: {
            identifier: '+919811111111',
            type: 'phone',
            cooldown_seconds: 60,
          },
        }),
      });
    });

    // Intercept backend OTP verify API
    await page.route('**/api/v1/auth/otp/verify', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: {
              id: 1,
              name: 'Ramesh Sharma',
              role: 'admin',
              phone: '+919811111111',
            },
            tokens: {
              access_token: 'fake-admin-jwt-token',
              token_type: 'Bearer',
              expires_in: 900,
            },
          },
        }),
      });
    });

    await page.goto('/login.html');

    // Step 1: Request OTP
    const input = page.locator('#login-identifier');
    await input.fill('9811111111');
    await page.locator('#send-otp-btn').click();

    // Verify Step 2 is shown
    const verifyForm = page.locator('#otp-verify-form');
    await expect(verifyForm).toBeVisible();
    await expect(page.locator('#sent-target-label')).toContainText('+919811111111');

    // Verify 60s resend timer is running
    await expect(page.locator('#resend-timer-text')).toContainText(/Resend code in \d+s/);

    // Step 2: Fill OTP 6 digits
    const digits = ['1', '2', '3', '4', '5', '6'];
    for (let i = 0; i < 6; i++) {
      await page.locator(`#otp-digit-${i}`).fill(digits[i]);
    }

    // Should redirect to admin dashboard for admin user
    await expect(page).toHaveURL(/admin\/index\.html/);
  });

  test('customer role redirects to store homepage', async ({ page }) => {
    await page.route('**/api/v1/auth/otp/request', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: { identifier: '+919833333333', type: 'phone', cooldown_seconds: 60 },
        }),
      });
    });

    await page.route('**/api/v1/auth/otp/verify', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: {
              id: 3,
              name: 'Rahul Gupta',
              role: 'customer',
              phone: '+919833333333',
            },
            tokens: {
              access_token: 'fake-customer-jwt-token',
            },
          },
        }),
      });
    });

    await page.goto('/login.html');

    await page.locator('#login-identifier').fill('9833333333');
    await page.locator('#send-otp-btn').click();

    // Fill OTP
    for (let i = 0; i < 6; i++) {
      await page.locator(`#otp-digit-${i}`).fill(String(i + 1));
    }

    // Should redirect to customer home
    await expect(page).toHaveURL(/index\.html|\/$/);
  });

  test('rate limit 429 shows countdown banner and disables buttons', async ({ page }) => {
    await page.route('**/api/v1/auth/otp/request', async (route) => {
      await route.fulfill({
        status: 429,
        headers: { 'Retry-After': '42' },
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          error: {
            code: 'RATE_LIMITED',
            message: 'Bahut zyada requests. 42 sec baad try karein.',
            retry_after: 42,
          },
        }),
      });
    });

    await page.goto('/login.html');

    await page.locator('#login-identifier').fill('9811111111');
    await page.locator('#send-otp-btn').click();

    // Verify rate limit banner is shown
    const rateLimitBanner = page.locator('#rate-limit-banner');
    await expect(rateLimitBanner).toBeVisible();
    await expect(page.locator('#rate-limit-seconds')).toContainText(/\d+s/);
  });
});

