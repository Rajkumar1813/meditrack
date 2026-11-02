import { api } from '../core/api.js';
import { clear, el, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { initAdminCommon } from './admin-common.js';

let state = {
  status: 'pending',
  selectedRx: null,
  zoom: 1,
  rotation: 0,
};

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('prescriptions');
  if (!user) return;

  setupEventListeners();
  await loadPrescriptions();
});

function setupEventListeners() {
  const btnPending = document.getElementById('rx-filter-pending');
  const btnAll = document.getElementById('rx-filter-all');

  btnPending?.addEventListener('click', () => {
    state.status = 'pending';
    btnPending.classList.add('bg-white', 'dark:bg-slate-900', 'text-primary-600', 'dark:text-primary-400', 'font-bold', 'shadow-sm');
    btnPending.classList.remove('text-slate-600', 'dark:text-slate-400');
    btnAll?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-primary-600', 'dark:text-primary-400', 'font-bold', 'shadow-sm');
    btnAll?.classList.add('text-slate-600', 'dark:text-slate-400');
    loadPrescriptions();
  });

  btnAll?.addEventListener('click', () => {
    state.status = '';
    btnAll.classList.add('bg-white', 'dark:bg-slate-900', 'text-primary-600', 'dark:text-primary-400', 'font-bold', 'shadow-sm');
    btnAll.classList.remove('text-slate-600', 'dark:text-slate-400');
    btnPending?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-primary-600', 'dark:text-primary-400', 'font-bold', 'shadow-sm');
    btnPending?.classList.add('text-slate-600', 'dark:text-slate-400');
    loadPrescriptions();
  });

  // Modal close
  document.getElementById('btn-close-rx-modal')?.addEventListener('click', closeReviewModal);

  // Zoom & Rotate controls
  document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
    state.zoom = Math.min(state.zoom + 0.25, 4);
    applyTransform();
  });

  document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
    state.zoom = Math.max(state.zoom - 0.25, 0.5);
    applyTransform();
  });

  document.getElementById('btn-rotate')?.addEventListener('click', () => {
    state.rotation = (state.rotation + 90) % 360;
    applyTransform();
  });

  document.getElementById('btn-reset-view')?.addEventListener('click', () => {
    state.zoom = 1;
    state.rotation = 0;
    applyTransform();
  });

  // Decision radio toggle
  const actionRadios = document.querySelectorAll('input[name="rx_action"]');
  actionRadios.forEach((radio) => {
    radio.addEventListener('change', (e) => {
      const validGroup = document.getElementById('rx-valid-group');
      if (validGroup) {
        if (e.target.value === 'approve') {
          validGroup.classList.remove('hidden');
        } else {
          validGroup.classList.add('hidden');
        }
      }
    });
  });

  // Form submit
  document.getElementById('form-rx-decision')?.addEventListener('submit', handleSubmitReview);
}

function applyTransform() {
  const img = document.getElementById('rx-preview-image');
  if (img) {
    img.style.transform = `scale(${state.zoom}) rotate(${state.rotation}deg)`;
  }
}

async function loadPrescriptions() {
  const tbody = document.getElementById('prescriptions-table-body');
  if (!tbody) return;
  clear(tbody);

  tbody.appendChild(
    el('tr', {}, [el('td', { colSpan: 6, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading prescriptions...'])])
  );

  const queryParams = new URLSearchParams();
  if (state.status) queryParams.set('status', state.status);

  try {
    const res = await api.get(`/admin/prescriptions?${queryParams.toString()}`);
    const items = res?.data || [];
    clear(tbody);

    if (items.length === 0) {
      tbody.appendChild(
        el('tr', {}, [
          el('td', { colSpan: 6, className: 'p-12 text-center text-slate-400 text-xs' }, [
            state.status === 'pending'
              ? 'Prescription queue is clear! No pending uploads to review.'
              : 'No prescriptions found in the database.',
          ]),
        ])
      );
      return;
    }

    items.forEach((rx) => {
      const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

      // Rx ID
      const tdId = el('td', { className: 'py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white' }, [
        `RX-${rx.id}`,
      ]);

      // Customer
      const tdCust = el('td', { className: 'py-3.5 px-4 font-medium text-slate-800 dark:text-slate-200' }, [
        rx.customer_name || (rx.customer_phone ? rx.customer_phone : `User #${rx.user_id}`),
      ]);

      // Date
      const tdDate = el('td', { className: 'py-3.5 px-4 text-slate-500 font-mono text-[11px]' }, [
        rx.created_at ? new Date(rx.created_at).toLocaleString() : '-',
      ]);

      // Files Count
      const filesCount = Array.isArray(rx.files) ? rx.files.length : 1;
      const tdFiles = el('td', { className: 'py-3.5 px-4 text-center' }, [
        el('span', { className: 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }, [
          `${filesCount} File${filesCount > 1 ? 's' : ''}`,
        ]),
      ]);

      // Status
      const tdStatus = el('td', { className: 'py-3.5 px-4 text-center' });
      const s = (rx.status || 'pending').toLowerCase();
      let statusCls = 'bg-amber-50 text-amber-700';
      if (s === 'approved') statusCls = 'bg-emerald-50 text-emerald-700';
      else if (s === 'rejected') statusCls = 'bg-red-50 text-red-700';

      tdStatus.appendChild(
        el('span', { className: `px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${statusCls}` }, [s])
      );

      // Review Action
      const tdAction = el('td', { className: 'py-3.5 px-4 text-right' });
      const reviewBtn = el(
        'button',
        {
          className:
            'px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 hover:bg-primary-100 transition',
        },
        ['Review & Stamp']
      );
      reviewBtn.addEventListener('click', () => openReviewModal(rx.id));
      tdAction.appendChild(reviewBtn);

      tr.appendChild(tdId);
      tr.appendChild(tdCust);
      tr.appendChild(tdDate);
      tr.appendChild(tdFiles);
      tr.appendChild(tdStatus);
      tr.appendChild(tdAction);
      tbody.appendChild(tr);
    });
  } catch (err) {
    toast.error('Failed to load prescriptions');
  }
}

async function openReviewModal(rxId) {
  const modal = document.getElementById('rx-review-modal');
  modal?.classList.remove('hidden');

  state.zoom = 1;
  state.rotation = 0;
  applyTransform();

  try {
    const res = await api.get(`/admin/prescriptions/${rxId}`);
    const rx = res?.data;
    state.selectedRx = rx;

    setText('#modal-rx-title', `Prescription #RX-${rx.id}`);
    setText('#modal-rx-customer', `Uploaded by: ${rx.customer_name || 'Customer'} (${rx.customer_phone || ''})`);

    // Load prescription image (first file or primary file)
    const previewImg = document.getElementById('rx-preview-image');
    if (previewImg) {
      const fileUrl = rx.file_url || (rx.files && rx.files[0]?.file_url) || (rx.files && rx.files[0]?.url) || '';
      previewImg.src = fileUrl;
    }

    // Set default valid_until date (e.g. 6 months from today)
    const validUntilInput = document.getElementById('rx-valid-until');
    if (validUntilInput) {
      const d = new Date();
      d.setMonth(d.getMonth() + 6);
      validUntilInput.value = d.toISOString().split('T')[0];
    }
  } catch (err) {
    toast.error('Failed to load prescription review details');
  }
}

function closeReviewModal() {
  state.selectedRx = null;
  document.getElementById('rx-review-modal')?.classList.add('hidden');
}

async function handleSubmitReview(e) {
  e.preventDefault();
  if (!state.selectedRx) return;

  const action = document.querySelector('input[name="rx_action"]:checked')?.value || 'approve';
  const reason = document.getElementById('rx-review-reason')?.value.trim();
  const validUntil = document.getElementById('rx-valid-until')?.value;

  try {
    await api.patch(`/admin/prescriptions/${state.selectedRx.id}/review`, {
      action,
      reason: reason || (action === 'approve' ? 'Prescription verified and approved by registered pharmacist.' : 'Prescription unreadable or invalid.'),
      valid_until: action === 'approve' ? validUntil : null,
      items: [],
    });

    toast.success(`Prescription ${action}d successfully`);
    closeReviewModal();
    loadPrescriptions();
  } catch (err) {
    toast.error(err.message || 'Failed to submit review');
  }
}

