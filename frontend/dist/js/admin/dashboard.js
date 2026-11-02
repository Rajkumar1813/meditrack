import { api } from '../core/api.js';
import { clear, el, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { renderDonutChart, renderExpiryBars } from '../ui/charts.js';
import { initAdminCommon } from './admin-common.js';

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('dashboard');
  if (!user) return;

  await loadDashboardStats();
});

async function loadDashboardStats() {
  const currencySymbol = brand.get('app.currencySymbol', '₹');

  try {
    const res = await api.get('/admin/dashboard/stats');
    const data = res?.data || {};

    // 1. Stat Cards
    setText('#stat-total-medicines', String(data.total_medicines ?? 0));
    setText('#stat-total-units', Number(data.total_units ?? 0).toLocaleString());
    setText('#stat-low-stock', String(data.low_stock ?? 0));
    setText('#stat-expiring-30', String(data.expiring_30d ?? 0));
    setText('#stat-expired', String(data.expired ?? 0));
    setText('#stat-stock-value', `${currencySymbol}${Number(data.stock_value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`);

    setText('#stat-orders-today', String(data.orders_today ?? 0));
    setText('#stat-revenue-today', `${currencySymbol}${Number(data.revenue_today ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`);

    // 2. Active Alerts Banner / Cards
    renderAlerts(data.active_alerts || []);

    // 3. Category Donut Chart
    const donutContainer = document.getElementById('category-donut-container');
    if (donutContainer) {
      const categoryData = (data.category_breakdown || []).map((cat) => ({
        label: cat.name || 'General',
        value: Number(cat.stock_units || cat.medicine_count || 0),
      }));
      renderDonutChart(donutContainer, {
        data: categoryData,
        centerSubtitle: 'Total Stock',
      });
    }

    // 4. Expiry Overview Bars
    const expiryContainer = document.getElementById('expiry-bars-container');
    if (expiryContainer) {
      const exp = data.expiry_overview || {};
      const expiryItems = [
        {
          label: 'Safe (> 90 Days)',
          count: Number(exp.safe ?? 0),
          color: '#10b981',
          badgeClass: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300',
        },
        {
          label: 'Within 90 Days',
          count: Number(exp.within_90d ?? 0),
          color: '#f59e0b',
          badgeClass: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300',
        },
        {
          label: 'Expiring Soon (≤ 30 Days)',
          count: Number(exp.within_30d ?? 0),
          color: '#f97316',
          badgeClass: 'bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300',
        },
        {
          label: 'Expired (Action Required)',
          count: Number(exp.expired ?? 0),
          color: '#ef4444',
          badgeClass: 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300',
        },
      ];
      renderExpiryBars(expiryContainer, expiryItems);
    }

    // 5. Recently Added Batches
    renderRecentlyAdded(data.recently_added || [], currencySymbol);
  } catch (err) {
    toast.error('Failed to load dashboard metrics');
  }
}

function renderAlerts(alerts) {
  const container = document.getElementById('active-alerts-container');
  if (!container) return;
  clear(container);

  if (alerts.length === 0) {
    const safeBanner = el(
      'div',
      {
        className:
          'p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 flex items-center gap-3',
      },
      [
        el('span', { className: 'w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0' }),
        el('div', { className: 'text-xs text-emerald-800 dark:text-emerald-200 font-medium' }, [
          'All inventory batches are healthy. No critical expiry or threshold alerts.',
        ]),
      ]
    );
    container.appendChild(safeBanner);
    return;
  }

  const alertList = el('div', { className: 'grid grid-cols-1 md:grid-cols-3 gap-3' });

  alerts.forEach((alert) => {
    const isDanger = alert.severity === 'danger';
    const isWarning = alert.severity === 'warning';

    const cardClasses = isDanger
      ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900/40 text-red-800 dark:text-red-200'
      : isWarning
      ? 'bg-orange-50 dark:bg-orange-950/20 border-orange-200 dark:border-orange-900/40 text-orange-800 dark:text-orange-200'
      : 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/40 text-amber-800 dark:text-amber-200';

    const card = el('div', { className: `p-3.5 rounded-2xl border flex items-center justify-between gap-3 ${cardClasses}` });

    const left = el('div', { className: 'flex items-center gap-2.5 min-w-0' });
    const indicator = el('span', {
      className: `w-2.5 h-2.5 rounded-full shrink-0 ${isDanger ? 'bg-red-500' : isWarning ? 'bg-orange-500' : 'bg-amber-500'}`,
    });
    const textMsg = el('p', { className: 'text-xs font-semibold truncate' }, [alert.message]);
    left.appendChild(indicator);
    left.appendChild(textMsg);

    const actionBtn = el(
      'a',
      {
        href: alert.action_url || '#',
        className: 'shrink-0 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-white dark:bg-slate-900 shadow-sm hover:opacity-90 transition',
      },
      ['Review']
    );

    card.appendChild(left);
    card.appendChild(actionBtn);
    alertList.appendChild(card);
  });

  container.appendChild(alertList);
}

function renderRecentlyAdded(items, currencySymbol) {
  const container = document.getElementById('recently-added-container');
  if (!container) return;
  clear(container);

  if (items.length === 0) {
    const emptyRow = el(
      'div',
      { className: 'p-8 text-center text-xs text-slate-400 dark:text-slate-500' },
      ['No batches added recently']
    );
    container.appendChild(emptyRow);
    return;
  }

  const table = el('table', { className: 'w-full text-left text-xs' });
  const thead = el('thead', { className: 'text-[11px] font-semibold text-slate-400 border-b border-slate-100 dark:border-slate-800' });
  const headRow = el('tr', {}, [
    el('th', { className: 'pb-3 font-semibold' }, ['Medicine']),
    el('th', { className: 'pb-3 font-semibold' }, ['Batch']),
    el('th', { className: 'pb-3 font-semibold' }, ['Category']),
    el('th', { className: 'pb-3 font-semibold text-right' }, ['Units']),
    el('th', { className: 'pb-3 font-semibold text-right' }, ['Price']),
    el('th', { className: 'pb-3 font-semibold text-right' }, ['Expiry']),
  ]);
  thead.appendChild(headRow);
  table.appendChild(thead);

  const tbody = el('tbody', { className: 'divide-y divide-slate-100 dark:divide-slate-800/60' });

  items.forEach((item) => {
    const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition' });

    // Medicine
    const tdMed = el('td', { className: 'py-3 font-medium text-slate-900 dark:text-slate-100' }, [
      item.medicine_name || 'Unnamed',
    ]);

    // Batch
    const tdBatch = el('td', { className: 'py-3 font-mono text-[11px] text-slate-600 dark:text-slate-300' }, [
      item.batch_number || '-',
    ]);

    // Category
    const tdCat = el('td', { className: 'py-3 text-slate-500 dark:text-slate-400' }, [
      item.category_name || 'General',
    ]);

    // Units
    const tdQty = el('td', { className: 'py-3 text-right font-semibold text-slate-800 dark:text-slate-200' }, [
      String(item.quantity ?? 0),
    ]);

    // Price
    const tdPrice = el('td', { className: 'py-3 text-right text-slate-600 dark:text-slate-300 font-medium' }, [
      `${currencySymbol}${item.selling_price || 0}`,
    ]);

    // Expiry
    const tdExp = el('td', { className: 'py-3 text-right font-mono text-[11px] text-slate-500' }, [
      item.exp_date || '-',
    ]);

    tr.appendChild(tdMed);
    tr.appendChild(tdBatch);
    tr.appendChild(tdCat);
    tr.appendChild(tdQty);
    tr.appendChild(tdPrice);
    tr.appendChild(tdExp);
    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  container.appendChild(table);
}

