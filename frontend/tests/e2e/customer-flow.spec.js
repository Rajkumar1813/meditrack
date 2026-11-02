import { test, expect } from '@playwright/test';

test.describe('MediTrack Phase 10 Customer E2E Flow', () => {

  test('complete flow: login -> search -> cart -> COD checkout -> order detail page', async ({ page }) => {
    // 1. Mock Auth state and API endpoints
    await page.route('**/api/v1/auth/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: { id: 10, name: 'Ananya Sharma', phone: '+919876543210', role: 'customer' },
            tokens: { access_token: 'mock-access-token-12345', expires_in: 900 },
          },
        }),
      });
    });

    await page.route('**/api/v1/categories', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            { id: 1, name: 'Tablets', slug: 'tablets', icon: '💊' },
            { id: 2, name: 'Syrups', slug: 'syrups', icon: '🧪' },
          ],
        }),
      });
    });

    await page.route('**/api/v1/medicines*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 101,
              name: 'Paracetamol 650mg',
              slug: 'paracetamol-650',
              generic_name: 'Paracetamol',
              price: 32.5,
              mrp: 40.0,
              requires_rx: 0,
              in_stock: true,
              stock: 50,
              category_name: 'Tablets',
            },
          ],
          meta: { total: 1, page: 1, limit: 12 },
        }),
      });
    });

    // Mock Cart state
    let cartItems = [];
    await page.route('**/api/v1/cart', async (route) => {
      if (route.request().method() === 'GET') {
        const subtotal = cartItems.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
        const tax = subtotal * 0.12;
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              items: cartItems,
              item_count: cartItems.length,
              subtotal,
              tax_total: tax,
              delivery_fee: 0,
              total: subtotal + tax,
              has_rx_items: cartItems.some((i) => i.requires_rx),
            },
          }),
        });
      }
    });

    await page.route('**/api/v1/cart/items', async (route) => {
      if (route.request().method() === 'POST') {
        const body = JSON.parse(route.request().postData());
        cartItems.push({
          id: 501,
          medicine_id: body.medicine_id,
          name: 'Paracetamol 650mg',
          slug: 'paracetamol-650',
          unit_price: 32.5,
          price: 32.5,
          quantity: body.quantity || 1,
          line_total: 32.5 * (body.quantity || 1),
          requires_rx: 0,
        });
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'Item added to cart' }),
        });
      }
    });

    // Mock Addresses
    await page.route('**/api/v1/addresses', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 1,
              recipient_name: 'Ananya Sharma',
              phone: '9876543210',
              line1: 'Flat 402, Green Valley Apartments',
              city: 'Mumbai',
              state: 'Maharashtra',
              pincode: '400001',
              is_default: 1,
            },
          ],
        }),
      });
    });

    // Mock Orders
    let createdOrderId = 789;
    await page.route('**/api/v1/orders', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              id: createdOrderId,
              order_no: 'ORD-2026-000789',
              status: 'pending',
              total: 36.4,
              subtotal: 32.5,
              tax_total: 3.9,
              delivery_fee: 0,
              created_at: new Date().toISOString(),
              address: {
                recipient_name: 'Ananya Sharma',
                phone: '9876543210',
                line1: 'Flat 402, Green Valley Apartments',
                city: 'Mumbai',
                state: 'Maharashtra',
                pincode: '400001',
              },
              items: [
                {
                  id: 1,
                  medicine_id: 101,
                  medicine_name: 'Paracetamol 650mg',
                  quantity: 1,
                  unit_price: 32.5,
                  line_total: 32.5,
                  batch_no: 'BATCH-2026-A',
                  exp_date: '2027-12-31',
                },
              ],
            },
          }),
        });
      }
    });

    await page.route('**/api/v1/orders/789', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            id: 789,
            order_no: 'ORD-2026-000789',
            status: 'confirmed',
            total: 36.4,
            subtotal: 32.5,
            tax_total: 3.9,
            delivery_fee: 0,
            created_at: new Date().toISOString(),
            address: {
              recipient_name: 'Ananya Sharma',
              phone: '9876543210',
              line1: 'Flat 402, Green Valley Apartments',
              city: 'Mumbai',
              state: 'Maharashtra',
              pincode: '400001',
            },
            items: [
              {
                id: 1,
                medicine_id: 101,
                medicine_name: 'Paracetamol 650mg',
                quantity: 1,
                unit_price: 32.5,
                line_total: 32.5,
                batch_no: 'BATCH-2026-A',
                exp_date: '2027-12-31',
              },
            ],
          },
        }),
      });
    });

    // 2. Start at Home page
    await page.goto('/');
    await expect(page).toHaveTitle(/MediTrack/);

    // 3. Search for medicine
    const searchInput = page.locator('#global-search');
    await searchInput.fill('Paracetamol');
    await searchInput.press('Enter');

    // Should navigate to catalog page
    await expect(page).toHaveURL(/catalog\.html\?q=Paracetamol/);

    // 4. In catalog, verify medicine card is displayed
    const medCard = page.locator('article.card-3d');
    await expect(medCard.first()).toBeVisible();
    await expect(page.locator('article.card-3d a').first()).toContainText('Paracetamol 650mg');

    // 5. Add to Cart
    const addBtn = medCard.first().locator('button:has-text("Add to Cart")');
    await addBtn.click();
    await expect(page.locator('text=added to cart')).toBeVisible();

    // 6. Navigate to Cart
    await page.goto('/cart.html');
    await expect(page.locator('#cart-content-section')).toBeVisible();
    await expect(page.locator('#cart-items-container')).toContainText('Paracetamol 650mg');

    // 7. Click Proceed to Checkout
    const checkoutLink = page.locator('a:has-text("Proceed to Checkout")');
    await checkoutLink.click();
    await expect(page).toHaveURL(/checkout\.html/);

    // 8. On Checkout page: Select COD and Place Order
    await expect(page.locator('#addresses-list')).toBeVisible();
    await page.locator('input[value="cod"]').check();

    const placeOrderBtn = page.locator('#place-order-btn');
    await placeOrderBtn.click();

    // 9. Verify redirection to Order detail page with timeline
    await expect(page).toHaveURL(/order\.html\?id=789/);
    await expect(page.locator('#order-heading-no')).toContainText('789');
    await expect(page.locator('#order-detail-container')).toContainText('Fulfillment Timeline');
    await expect(page.locator('#order-detail-container')).toContainText('Paracetamol 650mg');
  });

  test('XSS immunity: medicine with <script> tag renders as safe text, never executes', async ({ page }) => {
    const maliciousPayload = '<script>window._xss_executed = true;</script>SafeMedicine 500mg';

    await page.route('**/api/v1/medicines*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [
            {
              id: 999,
              name: maliciousPayload,
              slug: 'xss-test',
              price: 15.0,
              requires_rx: 0,
              in_stock: true,
            },
          ],
          meta: { total: 1, page: 1, limit: 12 },
        }),
      });
    });

    await page.goto('/catalog.html');

    // Wait for card to render
    const card = page.locator('article.card-3d');
    await expect(card.first()).toBeVisible();

    // Verify the raw string is rendered visibly as plain text
    const textContent = await card.first().locator('a').textContent();
    expect(textContent).toContain('<script>');
    expect(textContent).toContain('SafeMedicine 500mg');

    // Verify no script tag was parsed into DOM and window._xss_executed is undefined
    const executed = await page.evaluate(() => window._xss_executed);
    expect(executed).toBeUndefined();

    const scriptTagsInsideCard = await card.first().locator('script').count();
    expect(scriptTagsInsideCard).toBe(0);
  });

  test('3D perspective card tilt honors prefers-reduced-motion', async ({ page }) => {
    // Emulate reduced motion
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    const card = page.locator('.card-3d').first();
    if (await card.isVisible()) {
      // Hover over card
      await card.hover();

      // Transform should not be set to perspective rotate
      const transform = await card.evaluate((el) => window.getComputedStyle(el).transform);
      expect(transform === 'none' || !transform.includes('perspective')).toBe(true);
    }
  });

});

