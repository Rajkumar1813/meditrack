import { api } from '../core/api.js';
import { clear, el, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { debounce } from '../core/debounce.js';
import { initAdminCommon } from './admin-common.js';

let state = {
  q: '',
  selectedCustomer: null,
};

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('customers');
  if (!user) return;

  setupEventListeners();
  await loadCustomers();
});

function setupEventListeners() {
  document.getElementById('filter-customer-search')?.addEventListener(
    'input',
    debounce((e) => {
      state.q = e.target.value.trim();
      loadCustomers();
    }, 300)
  );

  document.getElementById('btn-close-cust-modal')?.addEventListener('click', closeCustomerModal);
  document.getElementById('btn-toggle-block-cust')?.addEventListener('click', handleToggleBlock);
  document.getElementById('btn-export-cust-data')?.addEventListener('click', handleExportData);
}

async function loadCustomers() {
  const tbody = document.getElementById('customers-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 6, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading customers...'])])
  );

  const queryParams = new URLSearchParams();
  if (state.q) queryParams.set('q', state.q);

  try {
    const res = await api.get(`/admin/customers?${queryParams.toString()}`);
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [el('td', { colSpan: 6, className: 'p-12 text-center text-slate-400 text-xs' }, ['No customer accounts found.'])])
      );
      return;
    }

    const currencySymbol = brand.get('app.currencySymbol', '₹');

    items.forEach((cust) => {
      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Customer
      const tdCust = el('td', { className: 'py-3.5 px-4 font-semibold text-slate-900 dark:text-white' }, [
        cust.name || (cust.phone ? cust.phone : `User #${cust.id}`),
      ]);

      // Contact
      const tdContact = el('td', { className: 'py-3.5 px-4 text-slate-500 font-mono text-[11px]' });
      tdContact.appendChild(el('div', {}, [cust.phone || '-']));
      if (cust.email) tdContact.appendChild(el('div', { className: 'text-slate-400' }, [cust.email]));

      // Orders count
      const tdOrders = el('td', { className: 'py-3.5 px-4 text-right font-medium text-slate-700 dark:text-slate-300' }, [
        String(cust.orders_count ?? 0),
      ]);

      // Lifetime Spend
      const tdLtv = el('td', { className: 'py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white' }, [
        `${currencySymbol}${Number(cust.lifetime_value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`,
      ]);

      // Status
      const tdStatus = el('td', { className: 'py-3.5 px-4 text-center' });
      const isBlocked = !!cust.is_blocked;
      tdStatus.appendChild(
        el(
          'span',
          {
            className: `px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
              isBlocked ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'
            }`,
          },
          [isBlocked ? 'BLOCKED' : 'ACTIVE']
        )
      );

      // Actions
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const viewBtn = el(
        'button',
        { className: 'px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition' },
        ['View Details']
      );
      viewBtn.addEventListener('click', () => openCustomerModal(cust.id));
      tdAction.appendChild(viewBtn);

      tr.appendChild(tdCust);
      tr.appendChild(tdContact);
      tr.appendChild(tdOrders);
      tr.appendChild(tdLtv);
      tr.appendChild(tdStatus);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load customers');
  }
}

async function openCustomerModal(customerId) {
  const modal = document.getElementById('customer-modal');
  modal?.classList.remove('hidden');

  try {
    const res = await api.get(`/admin/customers/${customerId}`);
    const cust = res?.data;
    state.selectedCustomer = cust;

    setText('#modal-cust-name', cust.name || 'Customer');
    setText('#modal-cust-phone', `${cust.phone || ''} ${cust.email ? '• ' + cust.email : ''}`);
    setText('#modal-cust-orders-count', String(cust.orders_count ?? 0));

    const currencySymbol = brand.get('app.currencySymbol', '₹');
    setText('#modal-cust-ltv', `${currencySymbol}${Number(cust.lifetime_value || 0).toFixed(2)}`);

    // Toggle block button state
    const blockBtn = document.getElementById('btn-toggle-block-cust');
    if (blockBtn) {
      if (cust.is_blocked) {
        blockBtn.textContent = 'Unblock Customer';
        blockBtn.className = 'px-4 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold transition';
      } else {
        blockBtn.textContent = 'Block Customer';
        blockBtn.className = 'px-4 py-1.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 text-xs font-bold transition';
      }
    }

    // Addresses
    const addrContainer = document.getElementById('modal-cust-addresses');
    if (addrContainer) {
      clear(addrContainer);
      const addresses = cust.addresses || [];
      if (addresses.length === 0) {
        addrContainer.appendChild(el('div', { className: 'text-slate-400 text-xs italic' }, ['No saved addresses']));
      } else {
        addresses.forEach((ad) => {
          const card = el('div', { className: 'p-2.5 rounded-lg border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-[11px]' }, [
            el('div', { className: 'font-semibold text-slate-800 dark:text-slate-200' }, [ad.label || 'Home']),
            el('div', { className: 'text-slate-500' }, [`${ad.address_line1 || ''}, ${ad.city || ''} ${ad.pincode || ''}`]),
          ]);
          addrContainer.appendChild(card);
        });
      }
    }
  } catch (err) {
    toast.error('Failed to load customer profile');
  }
}

function closeCustomerModal() {
  state.selectedCustomer = null;
  document.getElementById('customer-modal')?.classList.add('hidden');
}

async function handleToggleBlock() {
  if (!state.selectedCustomer) return;
  const isBlocked = !!state.selectedCustomer.is_blocked;
  const action = isBlocked ? 'unblock' : 'block';

  if (!confirm(`Are you sure you want to ${action} this customer?`)) return;

  try {
    if (isBlocked) {
      await api.post(`/admin/customers/${state.selectedCustomer.id}/unblock`);
      toast.success('Customer unblocked');
    } else {
      await api.post(`/admin/customers/${state.selectedCustomer.id}/block`);
      toast.success('Customer blocked and active sessions revoked');
    }
    closeCustomerModal();
    loadCustomers();
  } catch (err) {
    toast.error(err.message || 'Action failed');
  }
}

async function handleExportData() {
  if (!state.selectedCustomer) return;
  try {
    toast.info('Downloading customer data report...');
    window.open(`/api/v1/admin/customers/${state.selectedCustomer.id}/export`, '_blank');
  } catch (err) {
    toast.error('Failed to export data');
  }
}

