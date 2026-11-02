import { api } from '../core/api.js';
import { clear, el, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { debounce } from '../core/debounce.js';
import { initAdminCommon } from './admin-common.js';

let state = {
  status: '',
  paymentStatus: '',
  q: '',
  selectedOrder: null,
};

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('orders');
  if (!user) return;

  setupEventListeners();
  await loadOrders();
});

function setupEventListeners() {
  document.getElementById('filter-order-status')?.addEventListener('change', (e) => {
    state.status = e.target.value;
    loadOrders();
  });

  document.getElementById('filter-payment-status')?.addEventListener('change', (e) => {
    state.paymentStatus = e.target.value;
    loadOrders();
  });

  document.getElementById('filter-order-search')?.addEventListener(
    'input',
    debounce((e) => {
      state.q = e.target.value.trim();
      loadOrders();
    }, 300)
  );

  document.getElementById('btn-close-order-drawer')?.addEventListener('click', () => {
    document.getElementById('order-drawer')?.classList.add('hidden');
  });

  document.getElementById('btn-open-status-modal')?.addEventListener('click', openStatusModal);
  document.getElementById('btn-close-status-modal')?.addEventListener('click', closeStatusModal);
  document.getElementById('btn-cancel-status-modal')?.addEventListener('click', closeStatusModal);
  document.getElementById('form-update-status')?.addEventListener('submit', handleUpdateStatus);
}

async function loadOrders() {
  const tbody = document.getElementById('orders-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 8, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading orders...'])])
  );

  const queryParams = new URLSearchParams();
  if (state.status) queryParams.set('status', state.status);
  if (state.paymentStatus) queryParams.set('payment_status', state.paymentStatus);
  if (state.q) queryParams.set('q', state.q);

  try {
    const res = await api.get(`/admin/orders?${queryParams.toString()}`);
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [
          el('td', { colSpan: 8, className: 'p-12 text-center text-slate-400 text-xs' }, [
            'No orders matching the specified filter criteria.',
          ]),
        ])
      );
      return;
    }

    const currencySymbol = brand.get('app.currencySymbol', '₹');

    items.forEach((ord) => {
      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Order ID
      const tdId = el('td', { className: 'py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white' }, [
        ord.order_number || `#${ord.id}`,
      ]);

      // Customer
      const tdCust = el('td', { className: 'py-3.5 px-4' });
      tdCust.appendChild(el('div', { className: 'font-semibold text-slate-800 dark:text-slate-200' }, [ord.customer_name || 'Customer']));
      tdCust.appendChild(el('div', { className: 'text-[11px] text-slate-400' }, [ord.customer_phone || '-']));

      // Date
      const tdDate = el('td', { className: 'py-3.5 px-4 text-slate-500 font-mono text-[11px]' }, [
        ord.created_at ? new Date(ord.created_at).toLocaleString() : '-',
      ]);

      // Items count
      const tdItems = el('td', { className: 'py-3.5 px-4 text-right font-medium text-slate-700 dark:text-slate-300' }, [
        String(ord.item_count || (ord.items ? ord.items.length : 1)),
      ]);

      // Total
      const tdTotal = el('td', { className: 'py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white' }, [
        `${currencySymbol}${Number(ord.total_amount || 0).toFixed(2)}`,
      ]);

      // Payment Status
      const tdPay = el('td', { className: 'py-3.5 px-4 text-center' });
      const isPaid = ord.payment_status === 'paid';
      tdPay.appendChild(
        el(
          'span',
          {
            className: `px-2 py-0.5 rounded-full text-[10px] font-bold ${
              isPaid
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300'
                : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300'
            }`,
          },
          [ord.payment_status ? ord.payment_status.toUpperCase() : 'PENDING']
        )
      );

      // Fulfillment Status
      const tdStatus = el('td', { className: 'py-3.5 px-4 text-center' });
      tdStatus.appendChild(createStatusChip(ord.status));

      // Actions
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const viewBtn = el(
        'button',
        { className: 'px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition' },
        ['Manage']
      );
      viewBtn.addEventListener('click', () => openOrderDetailDrawer(ord.id));
      tdAction.appendChild(viewBtn);

      tr.appendChild(tdId);
      tr.appendChild(tdCust);
      tr.appendChild(tdDate);
      tr.appendChild(tdItems);
      tr.appendChild(tdTotal);
      tr.appendChild(tdPay);
      tr.appendChild(tdStatus);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load orders');
  }
}

function createStatusChip(status) {
  const s = (status || 'pending').toLowerCase();
  let cls = 'bg-slate-100 text-slate-600';

  if (s === 'pending') cls = 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300';
  else if (s === 'confirmed') cls = 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300';
  else if (s === 'packed') cls = 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300';
  else if (s === 'shipped') cls = 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300';
  else if (s === 'delivered') cls = 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300';
  else if (s === 'cancelled') cls = 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300';

  return el('span', { className: `px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${cls}` }, [
    s,
  ]);
}

async function openOrderDetailDrawer(orderId) {
  const drawer = document.getElementById('order-drawer');
  drawer?.classList.remove('hidden');

  try {
    const res = await api.get(`/admin/orders/${orderId}`);
    const ord = res?.data;
    state.selectedOrder = ord;

    setText('#drawer-order-id', ord.order_number || `Order #${ord.id}`);
    setText(
      '#drawer-order-date',
      `Placed: ${ord.created_at ? new Date(ord.created_at).toLocaleString() : '-'}`
    );
    setText('#drawer-current-status-label', `Current: ${ord.status?.toUpperCase()}`);

    // Shipping address snapshot
    const addr = ord.shipping_address || ord.address || {};
    const addrText = typeof addr === 'string' ? addr : `${addr.address_line1 || ''}, ${addr.city || ''} ${addr.pincode || ''}`;
    setText('#drawer-shipping-address', addrText || 'No address provided');

    // Ordered items with batches
    renderDrawerItems(ord.items || []);

    // Timeline
    renderDrawerTimeline(ord.status_history || ord.timeline || []);
  } catch (err) {
    toast.error('Failed to load order details');
  }
}

function renderDrawerItems(items) {
  const container = document.getElementById('drawer-items-container');
  if (!container) return;
  clear(container);

  if (items.length === 0) {
    container.appendChild(el('div', { className: 'p-4 text-center text-slate-400' }, ['No items found in order']));
    return;
  }

  const currencySymbol = brand.get('app.currencySymbol', '₹');
  const table = el('table', { className: 'w-full text-left text-xs divide-y divide-slate-100 dark:divide-slate-800' });
  const thead = el('thead', { className: 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold' }, [
    el('tr', {}, [
      el('th', { className: 'p-2.5' }, ['Medicine']),
      el('th', { className: 'p-2.5 text-right' }, ['Qty']),
      el('th', { className: 'p-2.5 text-right' }, ['Price']),
      el('th', { className: 'p-2.5 text-right' }, ['Total']),
    ]),
  ]);
  table.appendChild(thead);

  const tbody = el('tbody', { className: 'divide-y divide-slate-100 dark:divide-slate-800' });
  items.forEach((it) => {
    const tr = el('tr', {}, [
      el('td', { className: 'p-2.5 font-medium text-slate-900 dark:text-white' }, [
        it.medicine_name || it.name || 'Medicine',
      ]),
      el('td', { className: 'p-2.5 text-right font-bold' }, [String(it.quantity || 1)]),
      el('td', { className: 'p-2.5 text-right text-slate-500' }, [`${currencySymbol}${it.unit_price || it.price || 0}`]),
      el('td', { className: 'p-2.5 text-right font-bold text-slate-900 dark:text-white' }, [
        `${currencySymbol}${it.subtotal || (it.quantity * it.unit_price) || 0}`,
      ]),
    ]);
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  container.appendChild(table);
}

function renderDrawerTimeline(history) {
  const container = document.getElementById('drawer-timeline-container');
  if (!container) return;
  clear(container);

  if (history.length === 0) {
    container.appendChild(el('div', { className: 'text-slate-400 text-xs italic' }, ['No status changes recorded']));
    return;
  }

  history.forEach((step) => {
    const row = el('div', { className: 'flex items-start gap-3 relative' });
    const dot = el('span', { className: 'w-2 h-2 rounded-full bg-primary-500 mt-1.5 shrink-0' });
    const content = el('div', { className: 'space-y-0.5' });
    const title = el('div', { className: 'font-semibold text-slate-800 dark:text-slate-200 capitalize' }, [step.status]);
    const meta = el('div', { className: 'text-[11px] text-slate-400 font-mono' }, [
      `${step.created_at ? new Date(step.created_at).toLocaleString() : ''} ${step.note ? '• ' + step.note : ''}`,
    ]);

    content.appendChild(title);
    content.appendChild(meta);
    row.appendChild(dot);
    row.appendChild(content);
    container.appendChild(row);
  });
}

function openStatusModal() {
  if (!state.selectedOrder) return;
  const modal = document.getElementById('status-modal');
  modal?.classList.remove('hidden');
}

function closeStatusModal() {
  document.getElementById('status-modal')?.classList.add('hidden');
}

async function handleUpdateStatus(e) {
  e.preventDefault();
  if (!state.selectedOrder) return;

  const newStatus = document.getElementById('update-order-status')?.value;
  const courier = document.getElementById('update-courier')?.value.trim();
  const trackingNo = document.getElementById('update-tracking')?.value.trim();
  const note = document.getElementById('update-note')?.value.trim();

  try {
    await api.patch(`/admin/orders/${state.selectedOrder.id}/status`, {
      status: newStatus,
      courier: courier || null,
      tracking_no: trackingNo || null,
      note: note || null,
    });

    toast.success(`Order status updated to ${newStatus}`);
    closeStatusModal();
    await openOrderDetailDrawer(state.selectedOrder.id);
    loadOrders();
  } catch (err) {
    toast.error(err.message || 'Invalid state transition');
  }
}

