import { test, expect } from '@playwright/test';

test.describe('MediTrack Phase 11 Admin Inventory OS Flow', () => {

  test.beforeEach(async ({ page }) => {
    page.on('console', (msg) => console.log(`[BROWSER ${msg.type()}]`, msg.text()));
    page.on('pageerror', (err) => console.log('[PAGE ERROR]', err.message));
    page.on('request', (req) => console.log('[BROWSER REQ]', req.method(), req.url()));
    page.on('response', (res) => {
      if (res.status() >= 400) {
        console.log('[HTTP RES ERROR]', res.status(), res.url());
      }
    });

    await page.route('**/api/v1/brand', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            app: {
              name: 'MediTrack',
              currencySymbol: '₹',
              currencyCode: 'INR',
              adminTagline: 'Inventory OS',
            },
          },
        }),
      });
    });

    await page.route('**/api/v1/cart', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: { items: [] } }),
      });
    });

    await page.route('**/api/v1/notifications*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: [], meta: { unread_count: 0 } }),
      });
    });
  });

  test('role-based menu: pharmacist has restricted settings, admin has full access', async ({ page }) => {
    // 1. Mock Auth as Pharmacist
    await page.route('**/api/v1/auth/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: { id: 2, name: 'Vikram Mehta', phone: '+919876500000', role: 'pharmacist' },
            tokens: { access_token: 'mock-pharm-token', expires_in: 900 },
          },
        }),
      });
    });

    await page.route('**/api/v1/admin/dashboard/stats', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            total_medicines: 48,
            total_units: 3200,
            stock_value: 125000,
            low_stock: 4,
            expiring_30d: 3,
            expired: 1,
            orders_today: 12,
            revenue_today: 4800,
            category_breakdown: [{ name: 'Antibiotics', stock_units: 450 }],
            expiry_overview: { safe: 40, within_90d: 4, within_30d: 3, expired: 1 },
            recently_added: [],
            active_alerts: [],
          },
        }),
      });
    });

    // Visit admin dashboard as pharmacist
    await page.goto('/admin/index.html');
    await page.waitForLoadState('networkidle');

    // Role badge should show Registered Pharmacist
    await expect(page.locator('#admin-user-role')).toContainText('Pharmacist');

    // Settings link must be hidden for pharmacist
    const settingsNav = page.locator('[data-admin-nav="settings"]');
    await expect(settingsNav).toHaveClass(/hidden/);

    // If pharmacist tries to access /admin/settings.html directly, guard must redirect
    await page.goto('/admin/settings.html');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/admin\/index\.html/);

    // 2. Now Mock Auth as Admin
    await page.route('**/api/v1/auth/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: { id: 1, name: 'Dr. Rajesh Patel', phone: '+919876543210', role: 'admin' },
            tokens: { access_token: 'mock-admin-token', expires_in: 900 },
          },
        }),
      });
    });

    await page.route('**/api/v1/admin/settings', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            shop_name: 'MediTrack Premier Pharmacy',
            owner_name: 'Dr. Rajesh Patel',
            support_email: 'admin@meditrack.example',
            support_phone: '+919876543210',
            address: 'Main Health Complex, Mumbai',
            gst_number: '27AABCU9603R1ZM',
            drug_license_no: 'MH-MZ2-458921',
            low_stock_threshold: 15,
            delivery_fee: 40,
          },
        }),
      });
    });

    // Revisit as admin
    await page.goto('/admin/index.html');
    await page.waitForLoadState('networkidle');

    // Settings link must now be visible
    await expect(page.locator('#admin-user-role')).toContainText('Owner / Admin');
    await expect(settingsNav).not.toHaveClass(/hidden/);

    // Admin can access /admin/settings.html
    await settingsNav.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/admin\/settings\.html/);
    await expect(page.locator('#setting-store-name')).toHaveValue('MediTrack Premier Pharmacy');
  });

  test('dashboard visual parity: 6 stat cards, inline SVG donut and bar charts', async ({ page }) => {
    await page.route('**/api/v1/auth/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: { id: 1, name: 'Admin', role: 'admin' },
            tokens: { access_token: 'mock-admin-token', expires_in: 900 },
          },
        }),
      });
    });

    await page.route('**/api/v1/admin/dashboard/stats', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            total_medicines: 120,
            total_units: 5400,
            stock_value: 284500,
            low_stock: 8,
            expiring_30d: 5,
            expired: 2,
            orders_today: 18,
            revenue_today: 7200,
            category_breakdown: [
              { name: 'Tablets', stock_units: 3200 },
              { name: 'Syrups', stock_units: 1200 },
              { name: 'Injectables', stock_units: 1000 },
            ],
            expiry_overview: { safe: 95, within_90d: 18, within_30d: 5, expired: 2 },
            recently_added: [
              { medicine_name: 'Amoxicillin 500mg', batch_number: 'AMX-01', quantity: 200, selling_price: 85, exp_date: '2027-05-01' },
            ],
            active_alerts: [
              { severity: 'danger', message: '2 batch(es) have expired and need disposal/return.', action_url: '/admin/expired.html' },
            ],
          },
        }),
      });
    });

    await page.goto('/admin/index.html');
    await page.waitForLoadState('networkidle');

    // 6 Stat cards checks
    await expect(page.locator('#stat-total-medicines')).toHaveText('120');
    await expect(page.locator('#stat-total-units')).toHaveText('5,400');
    await expect(page.locator('#stat-low-stock')).toHaveText('8');
    await expect(page.locator('#stat-expiring-30')).toHaveText('5');
    await expect(page.locator('#stat-expired')).toHaveText('2');
    await expect(page.locator('#stat-stock-value')).toContainText('2,84,500');

    // Orders today & revenue
    await expect(page.locator('#stat-orders-today')).toHaveText('18');
    await expect(page.locator('#stat-revenue-today')).toContainText('7,200');

    // Inline SVG Donut Chart
    const donutSvg = page.locator('#category-donut-container svg');
    await expect(donutSvg).toBeVisible();

    // Inline SVG Expiry Bars
    const expiryBars = page.locator('#expiry-bars-container');
    await expect(expiryBars).toContainText('Safe (> 90 Days)');
    await expect(expiryBars).toContainText('Expired (Action Required)');

    // Active alert
    await expect(page.locator('#active-alerts-container')).toContainText('2 batch(es) have expired');
  });

  test('complete pharmacy workflow: add medicine -> expiring -> order fulfillment (pending to shipped)', async ({ page }) => {
    // In-memory mock database state
    let medicinesList = [
      {
        id: 101,
        name: 'Paracetamol 650mg',
        generic_name: 'Paracetamol',
        category_id: 1,
        category_name: 'Analgesics',
        dosage_form: 'Tablet',
        strength: '650mg',
        requires_rx: 0,
        total_stock: 50,
        min_price: 32,
        batch_count: 1,
      },
    ];

    let batchesList = [
      {
        id: 501,
        medicine_id: 101,
        batch_number: 'PARA-2026',
        quantity: 50,
        mfg_date: '2026-01-01',
        exp_date: '2027-01-01',
        purchase_price: 25,
        selling_price: 32,
        mrp: 35,
        rack_name: 'Rack A1',
        shelf_name: 'Shelf 1',
      },
    ];

    let expiringList = [];

    let orderState = {
      id: 7001,
      order_number: 'ORD-7001',
      customer_name: 'Sunita Rao',
      customer_phone: '+919876512345',
      created_at: new Date().toISOString(),
      item_count: 2,
      total_amount: 160.0,
      payment_status: 'paid',
      status: 'pending',
      shipping_address: 'Flat 402, Sunshine Heights, Andheri West, Mumbai 400053',
      items: [
        { medicine_name: 'Azithromycin 500mg', quantity: 2, unit_price: 80, subtotal: 160 },
      ],
      status_history: [
        { status: 'pending', created_at: new Date().toISOString(), note: 'Order placed by customer' },
      ],
    };

    // Mock API Handlers
    await page.route('**/api/v1/auth/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            user: { id: 1, name: 'Admin Pharmacist', role: 'admin' },
            tokens: { access_token: 'mock-token', expires_in: 900 },
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
          data: [{ id: 1, name: 'Antibiotics' }, { id: 2, name: 'Analgesics' }],
        }),
      });
    });

    await page.route('**/api/v1/admin/racks', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [{ id: 1, name: 'Rack A1', zone: 'Main Store', shelves: [{ id: 1, shelf_number: '1' }] }],
        }),
      });
    });

    await page.route('**/api/v1/admin/shelves', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: [{ id: 1, shelf_number: '1', rack_id: 1 }],
        }),
      });
    });

    // Medicines CRUD routes (match subpaths including /batches)
    await page.route(/\/api\/v1\/admin\/medicines/, async (route) => {
      const method = route.request().method();
      const url = route.request().url();

      if (method === 'GET' && url.includes('/batches')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: batchesList }),
        });
      }

      if (method === 'POST' && url.includes('/batches')) {
        const payload = JSON.parse(route.request().postData());
        const newBatch = {
          id: batchesList.length + 501,
          medicine_id: 102,
          batch_number: payload.batch_number,
          quantity: payload.quantity,
          mfg_date: payload.mfg_date,
          exp_date: payload.expiry_date,
          purchase_price: payload.purchase_price,
          selling_price: payload.selling_price,
          mrp: payload.mrp,
          rack_name: 'Rack A1',
          shelf_name: 'Shelf 1',
        };
        batchesList.push(newBatch);
        // Also register in expiring
        expiringList.push({
          id: newBatch.id,
          medicine_name: 'Azithromycin 500mg',
          generic_name: 'Azithromycin',
          batch_number: payload.batch_number,
          exp_date: payload.expiry_date,
          quantity: payload.quantity,
          rack_name: 'Rack A1',
          shelf_name: 'Shelf 1',
        });
        return route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: newBatch }),
        });
      }

      if (method === 'POST') {
        const payload = JSON.parse(route.request().postData());
        const newMed = {
          id: 102,
          name: payload.name,
          generic_name: payload.generic_name,
          category_id: payload.category_id,
          category_name: 'Antibiotics',
          dosage_form: payload.dosage_form,
          strength: payload.strength,
          requires_rx: payload.requires_rx,
          total_stock: 0,
          min_price: 80,
          batch_count: 0,
        };
        medicinesList.push(newMed);
        return route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: newMed }),
        });
      }

      // Default GET medicines
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: medicinesList,
          meta: { total: medicinesList.length, from: 1, to: medicinesList.length, last_page: 1 },
        }),
      });
    });

    // Expiring route
    await page.route(/\/api\/v1\/admin\/expiring/, async (route) => {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: expiringList }),
      });
    });

    // Orders routes
    await page.route(/\/api\/v1\/admin\/orders/, async (route) => {
      const method = route.request().method();
      const url = route.request().url();

      if (method === 'PATCH' && url.includes('/status')) {
        const payload = JSON.parse(route.request().postData());
        orderState.status = payload.status;
        orderState.status_history.push({
          status: payload.status,
          created_at: new Date().toISOString(),
          note: payload.note || `Courier: ${payload.courier || 'None'} Tracking: ${payload.tracking_no || 'None'}`,
        });
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: orderState }),
        });
      }

      if (method === 'GET' && url.includes('/7001')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: orderState }),
        });
      }

      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: [orderState] }),
      });
    });

    // STEP 1: Go to Medicines page and Add a Medicine
    await page.goto('/admin/medicines.html');
    await page.waitForLoadState('networkidle');

    await page.locator('#btn-add-medicine').click();
    await expect(page.locator('#medicine-modal')).toBeVisible();

    await page.locator('#med-name').fill('Azithromycin 500mg');
    await page.locator('#med-generic').fill('Azithromycin');
    await page.locator('#med-category').selectOption('1');
    await page.locator('#med-mfg').fill('Cipla Ltd');
    await page.locator('#med-strength').fill('500mg');
    await page.locator('#med-rx').check();

    await page.locator('#btn-save-med').click();
    await expect(page.locator('#medicine-modal')).toBeHidden();

    // Verify new medicine is rendered in table
    await expect(page.locator('#medicines-table-body')).toContainText('Azithromycin 500mg');

    // STEP 2: Open Batches Drawer on the medicine and Add an expiring batch
    const batchesBtns = page.locator('#medicines-table-body button:has-text("Batches")');
    await batchesBtns.last().click();
    await expect(page.locator('#batch-drawer')).toBeVisible();

    // Expire 15 days from now
    const today = new Date();
    const in15d = new Date(today);
    in15d.setDate(today.getDate() + 15);
    const in15dStr = in15d.toISOString().split('T')[0];

    await page.locator('#batch-number').fill('AZI-EXP-15D');
    await page.locator('#batch-qty').fill('30');
    await page.locator('#batch-mfg-date').fill('2026-01-01');
    await page.locator('#batch-exp-date').fill(in15dStr);
    await page.locator('#batch-purchase').fill('60');
    await page.locator('#batch-selling').fill('80');
    await page.locator('#batch-mrp').fill('85');

    await page.locator('#btn-save-batch').click();

    // Wait for batch to appear in drawer
    await expect(page.locator('#batch-list-container')).toContainText('AZI-EXP-15D');
    await page.locator('#btn-close-batch-drawer').click();

    // STEP 3: Navigate to Expiring Soon page & verify the batch appears
    await page.goto('/admin/expiring.html');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#expiring-table-body')).toContainText('Azithromycin 500mg');
    await expect(page.locator('#expiring-table-body')).toContainText('AZI-EXP-15D');
    await expect(page.locator('#expiring-table-body')).toContainText('15 days');

    // STEP 4: Navigate to Orders page & progress status
    await page.goto('/admin/orders.html');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#orders-table-body')).toContainText('ORD-7001');
    await expect(page.locator('#orders-table-body')).toContainText(/pending/i);

    // Open Order details drawer
    await page.locator('#orders-table-body button:has-text("Manage")').first().click();
    await expect(page.locator('#order-drawer')).toBeVisible();

    // Transition PENDING -> CONFIRMED
    await page.locator('#btn-open-status-modal').click();
    await expect(page.locator('#status-modal')).toBeVisible();
    await page.locator('#update-order-status').selectOption('confirmed');
    await page.locator('#form-update-status button[type="submit"]').click();
    await expect(page.locator('#status-modal')).toBeHidden();
    await expect(page.locator('#drawer-current-status-label')).toContainText(/confirmed/i);

    // Transition CONFIRMED -> PACKED
    await page.locator('#btn-open-status-modal').click();
    await expect(page.locator('#status-modal')).toBeVisible();
    await page.locator('#update-order-status').selectOption('packed');
    await page.locator('#form-update-status button[type="submit"]').click();
    await expect(page.locator('#status-modal')).toBeHidden();
    await expect(page.locator('#drawer-current-status-label')).toContainText(/packed/i);

    // Transition PACKED -> SHIPPED with Courier & Tracking No
    await page.locator('#btn-open-status-modal').click();
    await expect(page.locator('#status-modal')).toBeVisible();
    await page.locator('#update-order-status').selectOption('shipped');
    await page.locator('#update-courier').fill('Blue Dart Express');
    await page.locator('#update-tracking').fill('BD-778899');
    await page.locator('#form-update-status button[type="submit"]').click();
    await expect(page.locator('#status-modal')).toBeHidden();

    // Verify final state is shipped in drawer and timeline
    await expect(page.locator('#drawer-current-status-label')).toContainText(/shipped/i);
    await expect(page.locator('#drawer-timeline-container')).toContainText(/shipped/i);
    await expect(page.locator('#drawer-timeline-container')).toContainText('Blue Dart Express');
  });

});

