import { api } from '../core/api.js';
import { clear, el, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { brand } from '../core/brand.js';
import { debounce } from '../core/debounce.js';
import { initAdminCommon } from './admin-common.js';

let state = {
  page: 1,
  limit: 15,
  q: '',
  category: '',
  rx: '',
  sort: 'name_asc',
  view: localStorage.getItem('meditrack_med_view') || 'table',
  totalPages: 1,
  categories: [],
  racks: [],
  shelves: [],
  activeMedicine: null,
  deleteTarget: null,
  importRows: [],
};

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('medicines');
  if (!user) return;

  // Check URL query param ?q=
  const urlParams = new URLSearchParams(window.location.search);
  const qParam = urlParams.get('q');
  if (qParam) {
    state.q = qParam;
    const filterSearch = document.getElementById('filter-search');
    if (filterSearch) filterSearch.value = qParam;
  }

  setupEventListeners();
  await loadCategories();
  await loadRacksAndShelves();
  await loadMedicines();
});

function setupEventListeners() {
  // View switcher
  const btnTable = document.getElementById('view-table-btn');
  const btnCards = document.getElementById('view-cards-btn');

  btnTable?.addEventListener('click', () => setView('table'));
  btnCards?.addEventListener('click', () => setView('cards'));

  // Search input debounced
  const filterSearch = document.getElementById('filter-search');
  if (filterSearch) {
    filterSearch.addEventListener(
      'input',
      debounce((e) => {
        state.q = e.target.value.trim();
        state.page = 1;
        loadMedicines();
      }, 300)
    );
  }

  // Category filter
  document.getElementById('filter-category')?.addEventListener('change', (e) => {
    state.category = e.target.value;
    state.page = 1;
    loadMedicines();
  });

  // Rx filter
  document.getElementById('filter-rx')?.addEventListener('change', (e) => {
    state.rx = e.target.value;
    state.page = 1;
    loadMedicines();
  });

  // Sort filter
  document.getElementById('filter-sort')?.addEventListener('change', (e) => {
    state.sort = e.target.value;
    state.page = 1;
    loadMedicines();
  });

  // Pagination buttons
  document.getElementById('btn-pag-prev')?.addEventListener('click', () => {
    if (state.page > 1) {
      state.page--;
      loadMedicines();
    }
  });

  document.getElementById('btn-pag-next')?.addEventListener('click', () => {
    if (state.page < state.totalPages) {
      state.page++;
      loadMedicines();
    }
  });

  // Print button
  document.getElementById('btn-print')?.addEventListener('click', () => {
    window.print();
  });

  // CSV Export
  document.getElementById('btn-export-csv')?.addEventListener('click', () => {
    window.open('/api/v1/admin/medicines/export', '_blank');
  });

  // Add Medicine button
  document.getElementById('btn-add-medicine')?.addEventListener('click', () => {
    openMedicineModal();
  });

  // Close Medicine modal
  document.getElementById('btn-close-med-modal')?.addEventListener('click', closeMedicineModal);
  document.getElementById('btn-cancel-med')?.addEventListener('click', closeMedicineModal);

  // Medicine Form Submit
  document.getElementById('form-medicine')?.addEventListener('submit', handleSaveMedicine);

  // Delete modal cancel & confirm
  document.getElementById('btn-cancel-delete')?.addEventListener('click', closeDeleteModal);
  document.getElementById('btn-confirm-delete')?.addEventListener('click', handleConfirmDelete);

  // Batch drawer close
  document.getElementById('btn-close-batch-drawer')?.addEventListener('click', closeBatchDrawer);

  // Add Batch Form submit
  document.getElementById('form-add-batch')?.addEventListener('submit', handleSaveBatch);

  // CSV Import Modal open & close
  document.getElementById('btn-open-import')?.addEventListener('click', openImportModal);
  document.getElementById('btn-close-import-modal')?.addEventListener('click', closeImportModal);
  document.getElementById('btn-cancel-import')?.addEventListener('click', closeImportModal);

  // Select CSV file
  const fileInput = document.getElementById('csv-file-input');
  document.getElementById('btn-select-csv')?.addEventListener('click', () => fileInput?.click());
  fileInput?.addEventListener('change', handleFileSelected);

  // Commit Import
  document.getElementById('btn-commit-import')?.addEventListener('click', handleCommitImport);
}

function setView(view) {
  state.view = view;
  localStorage.setItem('meditrack_med_view', view);

  const tableView = document.getElementById('medicines-table-view');
  const cardsView = document.getElementById('medicines-cards-view');
  const btnTable = document.getElementById('view-table-btn');
  const btnCards = document.getElementById('view-cards-btn');

  if (view === 'table') {
    tableView?.classList.remove('hidden');
    cardsView?.classList.add('hidden');
    btnTable?.classList.add('bg-white', 'dark:bg-slate-900', 'text-slate-900', 'dark:text-white', 'shadow-sm');
    btnCards?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-slate-900', 'dark:text-white', 'shadow-sm');
  } else {
    tableView?.classList.add('hidden');
    cardsView?.classList.remove('hidden');
    btnCards?.classList.add('bg-white', 'dark:bg-slate-900', 'text-slate-900', 'dark:text-white', 'shadow-sm');
    btnTable?.classList.remove('bg-white', 'dark:bg-slate-900', 'text-slate-900', 'dark:text-white', 'shadow-sm');
  }
}

async function loadCategories() {
  try {
    const res = await api.get('/categories');
    state.categories = res?.data || [];

    const filterSelect = document.getElementById('filter-category');
    const modalSelect = document.getElementById('med-category');

    if (filterSelect) {
      state.categories.forEach((cat) => {
        const opt = el('option', { value: String(cat.id) }, [cat.name]);
        filterSelect.appendChild(opt);
      });
    }

    if (modalSelect) {
      clear(modalSelect);
      const defOpt = el('option', { value: '' }, ['Select Category']);
      modalSelect.appendChild(defOpt);
      state.categories.forEach((cat) => {
        const opt = el('option', { value: String(cat.id) }, [cat.name]);
        modalSelect.appendChild(opt);
      });
    }
  } catch (_) {}
}

async function loadRacksAndShelves() {
  try {
    const [racksRes, shelvesRes] = await Promise.all([
      api.get('/admin/racks'),
      api.get('/admin/shelves'),
    ]);
    state.racks = racksRes?.data || [];
    state.shelves = shelvesRes?.data || [];

    const rackSelect = document.getElementById('batch-rack-id');
    const shelfSelect = document.getElementById('batch-shelf-id');

    if (rackSelect) {
      clear(rackSelect);
      rackSelect.appendChild(el('option', { value: '' }, ['No Rack Assigned']));
      state.racks.forEach((r) => {
        rackSelect.appendChild(el('option', { value: String(r.id) }, [`${r.name} (${r.zone || 'Zone'})`]));
      });
    }

    if (shelfSelect) {
      clear(shelfSelect);
      shelfSelect.appendChild(el('option', { value: '' }, ['No Shelf Assigned']));
      state.shelves.forEach((s) => {
        shelfSelect.appendChild(el('option', { value: String(s.id) }, [`Shelf ${s.shelf_number || s.id}`]));
      });
    }
  } catch (_) {}
}

async function loadMedicines() {
  const tableBody = document.getElementById('medicines-table-body');
  const cardsView = document.getElementById('medicines-cards-view');

  if (tableBody) {
    clear(tableBody);
    const loadingRow = el('tr', {}, [
      el('td', { colSpan: 8, className: 'p-8 text-center text-slate-400 text-xs' }, ['Loading medicines...']),
    ]);
    tableBody.appendChild(loadingRow);
  }

  const queryParams = new URLSearchParams({
    page: String(state.page),
    limit: String(state.limit),
  });
  if (state.q) queryParams.set('q', state.q);
  if (state.category) queryParams.set('category', state.category);
  if (state.rx !== '') queryParams.set('requires_rx', state.rx);
  if (state.sort) queryParams.set('sort', state.sort);

  try {
    const res = await api.get(`/admin/medicines?${queryParams.toString()}`);
    const items = res?.data || [];
    const meta = res?.meta || {};

    const total = meta.total ?? items.length;
    const from = meta.from ?? (items.length > 0 ? (state.page - 1) * state.limit + 1 : 0);
    const to = meta.to ?? (from + items.length - 1);
    state.totalPages = meta.last_page ?? Math.ceil(total / state.limit) ?? 1;

    // Update pagination counters
    setText('#pag-from', String(from));
    setText('#pag-to', String(to));
    setText('#pag-total', String(total));
    setText('#pag-current', `${state.page} / ${state.totalPages}`);

    const btnPrev = document.getElementById('btn-pag-prev');
    const btnNext = document.getElementById('btn-pag-next');
    if (btnPrev) btnPrev.disabled = state.page <= 1;
    if (btnNext) btnNext.disabled = state.page >= state.totalPages;

    renderTable(items);
    renderCards(items);
    setView(state.view);
  } catch (err) {
    toast.error('Failed to load medicines');
  }
}

function renderTable(items) {
  const tableBody = document.getElementById('medicines-table-body');
  if (!tableBody) return;
  clear(tableBody);

  if (items.length === 0) {
    const emptyRow = el('tr', {}, [
      el('td', { colSpan: 8, className: 'p-12 text-center text-slate-400 text-xs' }, [
        'No medicines match your search criteria.',
      ]),
    ]);
    tableBody.appendChild(emptyRow);
    return;
  }

  const currencySymbol = brand.get('app.currencySymbol', '₹');

  items.forEach((med) => {
    const tr = el('tr', { className: 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition' });

    // Name + Generic with highlight
    const tdName = el('td', { className: 'py-3.5 px-4' });
    const nameWrap = el('div', { className: 'font-bold text-slate-900 dark:text-slate-100 text-xs' });
    appendHighlightedText(nameWrap, med.name || 'Unnamed', state.q);
    const genWrap = el('div', { className: 'text-[11px] text-slate-400 truncate max-w-xs mt-0.5' });
    appendHighlightedText(genWrap, med.generic_name || med.manufacturer || '-', state.q);
    tdName.appendChild(nameWrap);
    tdName.appendChild(genWrap);

    // Category
    const tdCat = el('td', { className: 'py-3.5 px-4 text-slate-600 dark:text-slate-300' }, [
      med.category_name || 'General',
    ]);

    // Dosage Form / Strength
    const tdDosage = el('td', { className: 'py-3.5 px-4 text-slate-500' }, [
      `${med.dosage_form || 'Unit'} ${med.strength ? '• ' + med.strength : ''}`,
    ]);

    // Rx badge
    const tdRx = el('td', { className: 'py-3.5 px-4 text-center' });
    if (med.requires_rx) {
      tdRx.appendChild(
        el(
          'span',
          { className: 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-300' },
          ['Rx']
        )
      );
    } else {
      tdRx.appendChild(
        el(
          'span',
          { className: 'px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-400' },
          ['OTC']
        )
      );
    }

    // Available Units
    const stock = Number(med.total_stock ?? med.stock ?? 0);
    const minAlert = Number(med.min_stock_alert ?? 10);
    const isLow = stock <= minAlert;
    const tdUnits = el('td', { className: 'py-3.5 px-4 text-right' });
    const stockBadge = el(
      'span',
      {
        className: `font-bold ${isLow ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200'}`,
      },
      [String(stock)]
    );
    tdUnits.appendChild(stockBadge);

    // Price range
    const tdPrice = el('td', { className: 'py-3.5 px-4 text-right font-semibold text-slate-900 dark:text-white' }, [
      med.min_price ? `${currencySymbol}${med.min_price}` : '-',
    ]);

    // Batches count button
    const tdBatches = el('td', { className: 'py-3.5 px-4 text-center' });
    const batchBtn = el(
      'button',
      {
        className:
          'px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 hover:bg-primary-100 transition',
      },
      [`${med.batch_count ?? 0} Batches`]
    );
    batchBtn.addEventListener('click', () => openBatchDrawer(med));
    tdBatches.appendChild(batchBtn);

    // Actions
    const tdActions = el('td', { className: 'py-3.5 px-4 text-right' });
    const actionsWrap = el('div', { className: 'flex items-center justify-end gap-1.5' });

    // Edit button
    const editBtn = el(
      'button',
      {
        className: 'p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition',
        title: 'Edit Medicine',
      },
      [
        createSvgIcon(
          'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'
        ),
      ]
    );
    editBtn.addEventListener('click', () => openMedicineModal(med));

    // Delete button
    const delBtn = el(
      'button',
      {
        className: 'p-1.5 text-slate-400 hover:text-red-600 transition',
        title: 'Delete Medicine',
      },
      [
        createSvgIcon(
          'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'
        ),
      ]
    );
    delBtn.addEventListener('click', () => openDeleteModal(med));

    actionsWrap.appendChild(editBtn);
    actionsWrap.appendChild(delBtn);
    tdActions.appendChild(actionsWrap);

    tr.appendChild(tdName);
    tr.appendChild(tdCat);
    tr.appendChild(tdDosage);
    tr.appendChild(tdRx);
    tr.appendChild(tdUnits);
    tr.appendChild(tdPrice);
    tr.appendChild(tdBatches);
    tr.appendChild(tdActions);

    tableBody.appendChild(tr);
  });
}

function renderCards(items) {
  const cardsContainer = document.getElementById('medicines-cards-view');
  if (!cardsContainer) return;
  clear(cardsContainer);

  const currencySymbol = brand.get('app.currencySymbol', '₹');

  items.forEach((med) => {
    const card = el('div', {
      className:
        'p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between gap-4 card-3d',
    });

    const top = el('div', { className: 'space-y-1' });
    const titleRow = el('div', { className: 'flex items-start justify-between gap-2' });
    const nameEl = el('h3', { className: 'font-bold text-slate-900 dark:text-slate-100 text-sm' });
    appendHighlightedText(nameEl, med.name || 'Unnamed', state.q);
    titleRow.appendChild(nameEl);

    if (med.requires_rx) {
      titleRow.appendChild(
        el(
          'span',
          { className: 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-300 shrink-0' },
          ['Rx']
        )
      );
    }
    top.appendChild(titleRow);

    const genericEl = el('p', { className: 'text-xs text-slate-500 dark:text-slate-400' }, [
      med.generic_name || med.manufacturer || '-',
    ]);
    top.appendChild(genericEl);

    const metaRow = el('div', { className: 'pt-2 flex items-center justify-between text-xs border-t border-slate-100 dark:border-slate-800' });
    const stock = Number(med.total_stock ?? med.stock ?? 0);
    const stockSpan = el('span', { className: 'text-slate-600 dark:text-slate-300' }, [
      `Units: `,
      el('strong', { className: 'font-bold text-slate-900 dark:text-white' }, [String(stock)]),
    ]);
    const priceSpan = el('span', { className: 'font-bold text-primary-600 dark:text-primary-400' }, [
      med.min_price ? `${currencySymbol}${med.min_price}` : '-',
    ]);
    metaRow.appendChild(stockSpan);
    metaRow.appendChild(priceSpan);

    const bottom = el('div', { className: 'flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 gap-2' });
    const batchesBtn = el(
      'button',
      {
        className: 'px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold hover:bg-slate-200 transition',
      },
      [`Batches (${med.batch_count ?? 0})`]
    );
    batchesBtn.addEventListener('click', () => openBatchDrawer(med));

    const rightActions = el('div', { className: 'flex items-center gap-1' });
    const editBtn = el('button', { className: 'p-1.5 text-slate-400 hover:text-slate-700 transition' }, [
      createSvgIcon('M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'),
    ]);
    editBtn.addEventListener('click', () => openMedicineModal(med));

    const delBtn = el('button', { className: 'p-1.5 text-slate-400 hover:text-red-600 transition' }, [
      createSvgIcon('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'),
    ]);
    delBtn.addEventListener('click', () => openDeleteModal(med));

    rightActions.appendChild(editBtn);
    rightActions.appendChild(delBtn);

    bottom.appendChild(batchesBtn);
    bottom.appendChild(rightActions);

    card.appendChild(top);
    card.appendChild(metaRow);
    card.appendChild(bottom);
    cardsContainer.appendChild(card);
  });
}

function appendHighlightedText(parent, textContent, query) {
  if (!query || !query.trim()) {
    parent.appendChild(document.createTextNode(textContent));
    return;
  }

  const q = query.trim().toLowerCase();
  const lower = textContent.toLowerCase();
  let startIdx = 0;
  let matchIdx = lower.indexOf(q, startIdx);

  if (matchIdx === -1) {
    parent.appendChild(document.createTextNode(textContent));
    return;
  }

  while (matchIdx !== -1) {
    if (matchIdx > startIdx) {
      parent.appendChild(document.createTextNode(textContent.substring(startIdx, matchIdx)));
    }
    const matchStr = textContent.substring(matchIdx, matchIdx + q.length);
    const mark = el('mark', { className: 'bg-yellow-200 dark:bg-yellow-900/60 text-slate-900 dark:text-white px-0.5 rounded' }, [
      matchStr,
    ]);
    parent.appendChild(mark);

    startIdx = matchIdx + q.length;
    matchIdx = lower.indexOf(q, startIdx);
  }

  if (startIdx < textContent.length) {
    parent.appendChild(document.createTextNode(textContent.substring(startIdx)));
  }
}

function createSvgIcon(pathD) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'w-4 h-4');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('viewBox', '0 0 24 24');

  const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  p.setAttribute('stroke-linecap', 'round');
  p.setAttribute('stroke-linejoin', 'round');
  p.setAttribute('stroke-width', '2');
  p.setAttribute('d', pathD);
  svg.appendChild(p);

  return svg;
}

// Medicine Modal (Add / Edit)
function openMedicineModal(med = null) {
  const modal = document.getElementById('medicine-modal');
  const title = document.getElementById('modal-med-title');
  const form = document.getElementById('form-medicine');
  if (!modal || !form) return;

  form.reset();
  clearFieldErrors();

  if (med) {
    title.textContent = 'Edit Medicine';
    document.getElementById('med-id').value = med.id;
    document.getElementById('med-name').value = med.name || '';
    document.getElementById('med-generic').value = med.generic_name || '';
    document.getElementById('med-category').value = String(med.category_id || '');
    document.getElementById('med-mfg').value = med.manufacturer || '';
    document.getElementById('med-form').value = med.dosage_form || '';
    document.getElementById('med-strength').value = med.strength || '';
    document.getElementById('med-min-stock').value = String(med.min_stock_alert ?? 10);
    document.getElementById('med-gst').value = String(med.gst_rate ?? 12);
    document.getElementById('med-hsn').value = med.hsn_code || '';
    document.getElementById('med-rx').checked = !!med.requires_rx;
    document.getElementById('med-desc').value = med.description || '';
  } else {
    title.textContent = 'Add New Medicine';
    document.getElementById('med-id').value = '';
    document.getElementById('med-min-stock').value = '10';
    document.getElementById('med-gst').value = '12';
    document.getElementById('med-rx').checked = false;
  }

  modal.classList.remove('hidden');
}

function closeMedicineModal() {
  document.getElementById('medicine-modal')?.classList.add('hidden');
}

function clearFieldErrors() {
  ['name', 'generic', 'mfg'].forEach((field) => {
    const errSpan = document.getElementById(`err-med-${field}`);
    if (errSpan) {
      errSpan.textContent = '';
      errSpan.classList.add('hidden');
    }
  });
}

async function handleSaveMedicine(e) {
  e.preventDefault();
  clearFieldErrors();

  const id = document.getElementById('med-id')?.value;
  const payload = {
    name: document.getElementById('med-name')?.value.trim(),
    generic_name: document.getElementById('med-generic')?.value.trim(),
    category_id: Number(document.getElementById('med-category')?.value) || null,
    manufacturer: document.getElementById('med-mfg')?.value.trim(),
    dosage_form: document.getElementById('med-form')?.value.trim() || 'Tablet',
    strength: document.getElementById('med-strength')?.value.trim() || null,
    min_stock_alert: Number(document.getElementById('med-min-stock')?.value) || 10,
    gst_rate: Number(document.getElementById('med-gst')?.value) || 12,
    hsn_code: document.getElementById('med-hsn')?.value.trim() || '3004',
    requires_rx: document.getElementById('med-rx')?.checked ? 1 : 0,
    description: document.getElementById('med-desc')?.value.trim() || null,
  };

  try {
    if (id) {
      await api.put(`/admin/medicines/${id}`, payload);
      toast.success('Medicine updated successfully');
    } else {
      await api.post('/admin/medicines', payload);
      toast.success('Medicine created successfully');
    }
    closeMedicineModal();
    loadMedicines();
  } catch (err) {
    if (err.errors) {
      // Map server validation errors to field labels
      for (const [key, msg] of Object.entries(err.errors)) {
        const errSpan = document.getElementById(`err-med-${key}`);
        if (errSpan) {
          errSpan.textContent = Array.isArray(msg) ? msg[0] : String(msg);
          errSpan.classList.remove('hidden');
        }
      }
    }
    toast.error(err.message || 'Validation error saving medicine');
  }
}

// Delete Confirmation Modal
function openDeleteModal(med) {
  state.deleteTarget = med;
  const modal = document.getElementById('delete-modal');
  const nameEl = document.getElementById('delete-med-name');
  if (nameEl) nameEl.textContent = `Are you sure you want to delete "${med.name}"?`;
  modal?.classList.remove('hidden');
}

function closeDeleteModal() {
  state.deleteTarget = null;
  document.getElementById('delete-modal')?.classList.add('hidden');
}

async function handleConfirmDelete() {
  if (!state.deleteTarget) return;
  try {
    await api.delete(`/admin/medicines/${state.deleteTarget.id}`);
    toast.success('Medicine deleted');
    closeDeleteModal();
    loadMedicines();
  } catch (err) {
    toast.error(err.message || 'Failed to delete medicine');
  }
}

// Batch Drawer
async function openBatchDrawer(med) {
  state.activeMedicine = med;
  const drawer = document.getElementById('batch-drawer');
  const medName = document.getElementById('drawer-med-name');
  const medIdInput = document.getElementById('batch-med-id');
  const form = document.getElementById('form-add-batch');

  if (medName) medName.textContent = `Batches: ${med.name}`;
  if (medIdInput) medIdInput.value = med.id;
  form?.reset();

  drawer?.classList.remove('hidden');
  await loadBatches(med.id);
}

function closeBatchDrawer() {
  state.activeMedicine = null;
  document.getElementById('batch-drawer')?.classList.add('hidden');
}

async function loadBatches(medId) {
  const container = document.getElementById('batch-list-container');
  if (!container) return;
  clear(container);

  container.appendChild(el('div', { className: 'text-center py-6 text-slate-400 text-xs' }, ['Loading batches...']));

  try {
    const res = await api.get(`/admin/medicines/${medId}/batches`);
    const batches = res?.data || [];
    clear(container);

    if (batches.length === 0) {
      container.appendChild(
        el('div', { className: 'p-6 text-center text-xs text-slate-400' }, ['No batches registered for this medicine.'])
      );
      return;
    }

    const currencySymbol = brand.get('app.currencySymbol', '₹');

    batches.forEach((b) => {
      const card = el('div', {
        className: 'p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs space-y-2',
      });

      const topRow = el('div', { className: 'flex items-center justify-between' });
      const batchNo = el('span', { className: 'font-mono font-bold text-slate-900 dark:text-white' }, [
        b.batch_number || 'UNKNOWN',
      ]);
      const qtyBadge = el(
        'span',
        { className: 'px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' },
        [`${b.quantity} Units`]
      );
      topRow.appendChild(batchNo);
      topRow.appendChild(qtyBadge);

      const infoGrid = el('div', { className: 'grid grid-cols-2 gap-2 text-[11px] text-slate-500' });
      const expDate = el('div', {}, [`Exp: `, el('strong', { className: 'text-slate-700 dark:text-slate-300' }, [b.exp_date || '-'])]);
      const mfgDate = el('div', {}, [`Mfg: `, el('span', {}, [b.mfg_date || '-'])]);
      const priceInfo = el('div', {}, [`Price: ${currencySymbol}${b.selling_price} (MRP: ${currencySymbol}${b.mrp})`]);
      const locInfo = el('div', {}, [`Rack/Shelf: ${b.rack_name || b.rack_id || '-'} / ${b.shelf_name || b.shelf_id || '-'}`]);

      infoGrid.appendChild(expDate);
      infoGrid.appendChild(mfgDate);
      infoGrid.appendChild(priceInfo);
      infoGrid.appendChild(locInfo);

      card.appendChild(topRow);
      card.appendChild(infoGrid);
      container.appendChild(card);
    });
  } catch (err) {
    clear(container);
    container.appendChild(el('div', { className: 'text-center py-4 text-red-500 text-xs' }, ['Failed to load batches']));
  }
}

async function handleSaveBatch(e) {
  e.preventDefault();
  if (!state.activeMedicine) return;

  const mfg = document.getElementById('batch-mfg-date')?.value;
  const exp = document.getElementById('batch-exp-date')?.value;
  const purchase = parseFloat(document.getElementById('batch-purchase')?.value);
  const mrp = parseFloat(document.getElementById('batch-mrp')?.value);

  if (new Date(exp) <= new Date(mfg)) {
    toast.error('Expiry date must be after manufacturing date');
    return;
  }
  if (mrp < purchase) {
    toast.error('MRP must be greater than or equal to purchase price');
    return;
  }

  const payload = {
    batch_number: document.getElementById('batch-number')?.value.trim(),
    quantity: parseInt(document.getElementById('batch-qty')?.value, 10),
    mfg_date: mfg,
    expiry_date: exp,
    purchase_price: purchase,
    selling_price: parseFloat(document.getElementById('batch-selling')?.value),
    mrp: mrp,
    rack_id: parseInt(document.getElementById('batch-rack-id')?.value, 10) || null,
    shelf_id: parseInt(document.getElementById('batch-shelf-id')?.value, 10) || null,
  };

  try {
    await api.post(`/admin/medicines/${state.activeMedicine.id}/batches`, payload);
    toast.success('Batch added successfully');
    document.getElementById('form-add-batch')?.reset();
    await loadBatches(state.activeMedicine.id);
    loadMedicines();
  } catch (err) {
    toast.error(err.message || 'Failed to add batch');
  }
}

// CSV 2-Step Import
function openImportModal() {
  state.importRows = [];
  document.getElementById('import-step-upload')?.classList.remove('hidden');
  document.getElementById('import-step-preview')?.classList.add('hidden');
  const btnCommit = document.getElementById('btn-commit-import');
  if (btnCommit) btnCommit.disabled = true;

  document.getElementById('csv-file-input').value = '';
  document.getElementById('import-modal')?.classList.remove('hidden');
}

function closeImportModal() {
  document.getElementById('import-modal')?.classList.add('hidden');
}

async function handleFileSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  try {
    toast.info('Analyzing CSV file...');
    const res = await api.post('/admin/medicines/import/preview', formData);
    const data = res?.data || {};

    state.importRows = data.rows || [];
    const validCount = data.valid_count ?? state.importRows.filter((r) => r.is_valid).length;
    const errorCount = data.error_count ?? state.importRows.filter((r) => !r.is_valid).length;

    setText('#preview-row-count', `${state.importRows.length} Rows Analyzed`);
    setText('#preview-valid-count', `${validCount} Valid`);
    setText('#preview-error-count', `${errorCount} Errors`);

    renderImportPreview(state.importRows);

    document.getElementById('import-step-upload')?.classList.add('hidden');
    document.getElementById('import-step-preview')?.classList.remove('hidden');

    const btnCommit = document.getElementById('btn-commit-import');
    if (btnCommit) btnCommit.disabled = validCount === 0;
  } catch (err) {
    toast.error(err.message || 'Failed to preview CSV file');
  }
}

function renderImportPreview(rows) {
  const tbody = document.getElementById('import-preview-body');
  if (!tbody) return;
  clear(tbody);

  rows.slice(0, 50).forEach((r, idx) => {
    const tr = el('tr', { className: r.is_valid ? 'hover:bg-slate-50' : 'bg-red-50/40 text-red-700' });
    tr.appendChild(el('td', { className: 'p-2 font-mono text-[11px]' }, [String(idx + 1)]));
    tr.appendChild(el('td', { className: 'p-2 font-medium' }, [r.name || '-']));
    tr.appendChild(el('td', { className: 'p-2 font-mono' }, [r.batch_number || '-']));
    tr.appendChild(el('td', { className: 'p-2 font-mono' }, [r.exp_date || '-']));
    tr.appendChild(el('td', { className: 'p-2 text-right' }, [String(r.quantity ?? 0)]));

    const statusTd = el('td', { className: 'p-2 font-semibold' });
    if (r.is_valid) {
      statusTd.appendChild(el('span', { className: 'text-emerald-600' }, ['✓ Ready']));
    } else {
      statusTd.appendChild(el('span', { className: 'text-red-600 text-[11px]' }, [r.error || 'Invalid row']));
    }
    tr.appendChild(statusTd);
    tbody.appendChild(tr);
  });
}

async function handleCommitImport() {
  const updateExisting = document.getElementById('import-update-existing')?.checked;
  try {
    toast.info('Committing inventory import...');
    await api.post('/admin/medicines/import/commit', {
      rows: state.importRows.filter((r) => r.is_valid),
      update_existing: !!updateExisting,
    });
    toast.success('Inventory imported successfully');
    closeImportModal();
    loadMedicines();
  } catch (err) {
    toast.error(err.message || 'Failed to commit import');
  }
}

