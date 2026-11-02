import { api } from '../core/api.js';
import { clear, el } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { initAdminCommon } from './admin-common.js';

let currentDays = 30;

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('expiring');
  if (!user) return;

  const urlParams = new URLSearchParams(window.location.search);
  const daysParam = parseInt(urlParams.get('days') || '30', 10);
  currentDays = daysParam === 90 ? 90 : 30;

  setupTabs();
  await loadExpiringBatches();
});

function setupTabs() {
  const tab30 = document.getElementById('tab-30d');
  const tab90 = document.getElementById('tab-90d');

  tab30?.addEventListener('click', () => {
    currentDays = 30;
    updateTabStyles();
    loadExpiringBatches();
  });

  tab90?.addEventListener('click', () => {
    currentDays = 90;
    updateTabStyles();
    loadExpiringBatches();
  });

  updateTabStyles();
}

function updateTabStyles() {
  const tab30 = document.getElementById('tab-30d');
  const tab90 = document.getElementById('tab-90d');

  if (currentDays === 30) {
    tab30?.classList.add('bg-white', 'dark:bg-slate-900', 'text-orange-600', 'dark:text-orange-400', 'font-bold', 'shadow-sm');
    tab30?.classList.remove('text-slate-600', 'dark:text-slate-400');
    tab90?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-orange-600', 'dark:text-orange-400', 'font-bold', 'shadow-sm');
    tab90?.classList.add('text-slate-600', 'dark:text-slate-400');
  } else {
    tab90?.classList.add('bg-white', 'dark:bg-slate-900', 'text-amber-600', 'dark:text-amber-400', 'font-bold', 'shadow-sm');
    tab90?.classList.remove('text-slate-600', 'dark:text-slate-400');
    tab30?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-amber-600', 'dark:text-amber-400', 'font-bold', 'shadow-sm');
    tab30?.classList.add('text-slate-600', 'dark:text-slate-400');
  }
}

async function loadExpiringBatches() {
  const tbody = document.getElementById('expiring-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 7, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading expiring batches...'])])
  );

  try {
    const res = await api.get(`/admin/expiring?days=${currentDays}`);
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [
          el('td', { colSpan: 7, className: 'p-12 text-center text-slate-400 text-xs' }, [
            `No batches found expiring within ${currentDays} days.`,
          ]),
        ])
      );
      return;
    }

    const today = new Date();

    items.forEach((item) => {
      const expDate = new Date(item.exp_date);
      const diffTime = expDate - today;
      const daysLeft = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Medicine / Generic
      const tdMed = el('td', { className: 'py-3.5 px-4' });
      const name = el('div', { className: 'font-bold text-slate-900 dark:text-slate-100' }, [item.medicine_name || 'Unnamed']);
      const gen = el('div', { className: 'text-[11px] text-slate-400 mt-0.5' }, [item.generic_name || '-']);
      tdMed.appendChild(name);
      tdMed.appendChild(gen);

      // Batch
      const tdBatch = el('td', { className: 'py-3.5 px-4 font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300' }, [
        item.batch_number || '-',
      ]);

      // Expiry Date
      const tdExp = el('td', { className: 'py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400' }, [item.exp_date || '-']);

      // Days Left
      const tdDays = el('td', { className: 'py-3.5 px-4' });
      const isUrgent = daysLeft <= 30;
      const daysBadge = el(
        'span',
        {
          className: `px-2.5 py-1 rounded-full text-[10px] font-bold ${
            isUrgent
              ? 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-300'
              : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300'
          }`,
        },
        [`${daysLeft > 0 ? daysLeft + ' days' : 'Expired today'}`]
      );
      tdDays.appendChild(daysBadge);

      // Remaining Units
      const tdUnits = el('td', { className: 'py-3.5 px-4 text-right font-bold text-slate-900 dark:text-slate-100' }, [
        String(item.quantity ?? 0),
      ]);

      // Rack / Shelf
      const tdLoc = el('td', { className: 'py-3.5 px-4 text-slate-500' }, [
        `${item.rack_name || 'No Rack'} / ${item.shelf_name || 'No Shelf'}`,
      ]);

      // Actions
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const disposeBtn = el(
        'button',
        {
          className:
            'px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 hover:bg-red-100 transition',
        },
        ['Mark Return / Dispose']
      );
      disposeBtn.addEventListener('click', () => handleDisposeAction(item.id));
      tdAction.appendChild(disposeBtn);

      tr.appendChild(tdMed);
      tr.appendChild(tdBatch);
      tr.appendChild(tdExp);
      tr.appendChild(tdDays);
      tr.appendChild(tdUnits);
      tr.appendChild(tdLoc);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load expiring inventory');
  }
}

async function handleDisposeAction(batchId) {
  if (!confirm('Mark this batch for return to supplier or safe pharmaceutical disposal?')) return;
  try {
    await api.post(`/admin/batches/${batchId}/dispose`, { action: 'return' });
    toast.success('Batch marked for return');
    loadExpiringBatches();
  } catch (err) {
    toast.error(err.message || 'Failed to update batch');
  }
}

