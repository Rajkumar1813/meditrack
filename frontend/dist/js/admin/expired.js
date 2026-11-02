import { api } from '../core/api.js';
import { clear, el } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { initAdminCommon } from './admin-common.js';

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('expired');
  if (!user) return;

  await loadExpiredBatches();
});

async function loadExpiredBatches() {
  const tbody = document.getElementById('expired-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 7, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading expired batches...'])])
  );

  try {
    const res = await api.get('/admin/expired');
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [
          el('td', { colSpan: 7, className: 'p-12 text-center text-slate-400 text-xs' }, [
            'No expired batches found. All active stock is within validity.',
          ]),
        ])
      );
      return;
    }

    const currencySymbol = brand.get('app.currencySymbol', '₹');

    items.forEach((item) => {
      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Medicine
      const tdMed = el('td', { className: 'py-3.5 px-4' });
      const name = el('div', { className: 'font-bold text-slate-900 dark:text-slate-100' }, [item.medicine_name || 'Unnamed']);
      const gen = el('div', { className: 'text-[11px] text-slate-400 mt-0.5' }, [item.generic_name || '-']);
      tdMed.appendChild(name);
      tdMed.appendChild(gen);

      // Batch
      const tdBatch = el('td', { className: 'py-3.5 px-4 font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300' }, [
        item.batch_number || '-',
      ]);

      // Expiry Date (Red font)
      const tdExp = el('td', { className: 'py-3.5 px-4 font-mono font-semibold text-red-600 dark:text-red-400' }, [
        item.exp_date || '-',
      ]);

      // Units
      const tdUnits = el('td', { className: 'py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white' }, [
        String(item.quantity ?? 0),
      ]);

      // Loss / Stock Value
      const lossVal = Number((item.quantity || 0) * (item.purchase_price || 0)).toFixed(2);
      const tdValue = el('td', { className: 'py-3.5 px-4 text-right font-semibold text-slate-600 dark:text-slate-300' }, [
        `${currencySymbol}${lossVal}`,
      ]);

      // Location
      const tdLoc = el('td', { className: 'py-3.5 px-4 text-slate-500' }, [
        `${item.rack_name || 'No Rack'} / ${item.shelf_name || 'No Shelf'}`,
      ]);

      // Actions (Return or Dispose)
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const actionsWrap = el('div', { className: 'flex items-center justify-end gap-2' });

      const returnBtn = el(
        'button',
        {
          className:
            'px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-300 hover:bg-orange-100 transition',
        },
        ['Return']
      );
      returnBtn.addEventListener('click', () => handleDisposalAction(item.id, 'return'));

      const disposeBtn = el(
        'button',
        {
          className:
            'px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-300 hover:bg-red-100 transition',
        },
        ['Dispose']
      );
      disposeBtn.addEventListener('click', () => handleDisposalAction(item.id, 'dispose'));

      actionsWrap.appendChild(returnBtn);
      actionsWrap.appendChild(disposeBtn);
      tdAction.appendChild(actionsWrap);

      tr.appendChild(tdMed);
      tr.appendChild(tdBatch);
      tr.appendChild(tdExp);
      tr.appendChild(tdUnits);
      tr.appendChild(tdValue);
      tr.appendChild(tdLoc);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load expired inventory');
  }
}

async function handleDisposalAction(batchId, actionType) {
  const actionLabel = actionType === 'return' ? 'return to supplier' : 'safe disposal';
  if (!confirm(`Confirm ${actionLabel} for this batch? This will remove units from active stock.`)) return;

  try {
    await api.post(`/admin/batches/${batchId}/dispose`, { action: actionType });
    toast.success(`Batch successfully recorded as ${actionType}ed`);
    loadExpiredBatches();
  } catch (err) {
    toast.error(err.message || 'Action failed');
  }
}

