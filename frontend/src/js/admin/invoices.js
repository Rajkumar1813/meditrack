import { api } from '../core/api.js';
import { clear, el } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { debounce } from '../core/debounce.js';
import { initAdminCommon } from './admin-common.js';

let state = {
  q: '',
};

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('invoices');
  if (!user) return;

  setupEventListeners();
  await loadInvoices();
});

function setupEventListeners() {
  document.getElementById('filter-invoice-search')?.addEventListener(
    'input',
    debounce((e) => {
      state.q = e.target.value.trim();
      loadInvoices();
    }, 300)
  );
}

async function loadInvoices() {
  const tbody = document.getElementById('invoices-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 7, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading invoices...'])])
  );

  const queryParams = new URLSearchParams();
  if (state.q) queryParams.set('q', state.q);

  try {
    const res = await api.get(`/admin/invoices?${queryParams.toString()}`);
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [el('td', { colSpan: 7, className: 'p-12 text-center text-slate-400 text-xs' }, ['No GST invoices found.'])])
      );
      return;
    }

    const currencySymbol = brand.get('app.currencySymbol', '₹');

    items.forEach((inv) => {
      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Invoice Number
      const tdInvNo = el('td', { className: 'py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white' }, [
        inv.invoice_number || `INV-${inv.id}`,
      ]);

      // Order No
      const tdOrderNo = el('td', { className: 'py-3.5 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300' }, [
        inv.order_number || `#${inv.order_id}`,
      ]);

      // Customer
      const tdCust = el('td', { className: 'py-3.5 px-4 font-medium text-slate-800 dark:text-slate-200' }, [
        inv.customer_name || 'Customer',
      ]);

      // Date
      const tdDate = el('td', { className: 'py-3.5 px-4 text-slate-500 font-mono text-[11px]' }, [
        inv.created_at ? new Date(inv.created_at).toLocaleDateString() : '-',
      ]);

      // Tax Amount
      const tdTax = el('td', { className: 'py-3.5 px-4 text-right text-slate-500' }, [
        `${currencySymbol}${Number(inv.tax_amount || 0).toFixed(2)}`,
      ]);

      // Total Amount
      const tdTotal = el('td', { className: 'py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white' }, [
        `${currencySymbol}${Number(inv.total_amount || 0).toFixed(2)}`,
      ]);

      // Actions
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const actionsWrap = el('div', { className: 'flex items-center justify-end gap-2' });

      // Download PDF
      const dlBtn = el(
        'button',
        {
          className:
            'px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 hover:bg-primary-100 transition flex items-center gap-1',
        },
        [
          createSvg('M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'),
          'PDF',
        ]
      );
      dlBtn.addEventListener('click', () => {
        window.open(`/api/v1/admin/invoices/${inv.id}/download`, '_blank');
      });

      // Resend link
      const resendBtn = el(
        'button',
        {
          className: 'px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition',
        },
        ['Resend']
      );
      resendBtn.addEventListener('click', () => handleResendInvoice(inv.id));

      actionsWrap.appendChild(dlBtn);
      actionsWrap.appendChild(resendBtn);
      tdAction.appendChild(actionsWrap);

      tr.appendChild(tdInvNo);
      tr.appendChild(tdOrderNo);
      tr.appendChild(tdCust);
      tr.appendChild(tdDate);
      tr.appendChild(tdTax);
      tr.appendChild(tdTotal);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load invoices');
  }
}

async function handleResendInvoice(invId) {
  try {
    await api.post(`/admin/invoices/${invId}/resend`);
    toast.success('Invoice dispatch notification queued for customer');
  } catch (err) {
    toast.error(err.message || 'Failed to resend invoice');
  }
}

function createSvg(d) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'w-3.5 h-3.5');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('viewBox', '0 0 24 24');

  const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  p.setAttribute('stroke-linecap', 'round');
  p.setAttribute('stroke-linejoin', 'round');
  p.setAttribute('stroke-width', '2');
  p.setAttribute('d', d);
  svg.appendChild(p);
  return svg;
}

