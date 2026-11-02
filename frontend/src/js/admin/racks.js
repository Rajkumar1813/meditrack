import { api } from '../core/api.js';
import { clear, el } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { initAdminCommon } from './admin-common.js';

let racks = [];

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('racks');
  if (!user) return;

  setupEventListeners();
  await loadRacks();
});

function setupEventListeners() {
  document.getElementById('btn-add-rack')?.addEventListener('click', () => openRackModal());
  document.getElementById('btn-close-rack-modal')?.addEventListener('click', closeRackModal);
  document.getElementById('btn-cancel-rack')?.addEventListener('click', closeRackModal);
  document.getElementById('form-rack')?.addEventListener('submit', handleSaveRack);

  document.getElementById('btn-close-rack-drawer')?.addEventListener('click', () => {
    document.getElementById('rack-medicines-drawer')?.classList.add('hidden');
  });
}

async function loadRacks() {
  const container = document.getElementById('racks-grid');
  if (!container) return;
  clear(container);

  try {
    const res = await api.get('/admin/racks');
    racks = res?.data || [];

    if (racks.length === 0) {
      container.appendChild(
        el('div', { className: 'col-span-full p-12 text-center text-xs text-slate-400' }, [
          'No storage racks created yet. Click "Add Rack" to create your pharmacy locations.',
        ])
      );
      return;
    }

    racks.forEach((rack) => {
      const card = el('div', {
        className: 'p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between gap-4 card-3d',
      });

      const top = el('div', { className: 'space-y-1' });
      const headerRow = el('div', { className: 'flex items-start justify-between' });
      const name = el('h3', { className: 'text-base font-bold text-slate-900 dark:text-white' }, [rack.name]);
      const zoneBadge = el(
        'span',
        { className: 'px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300' },
        [rack.zone || 'General']
      );
      headerRow.appendChild(name);
      headerRow.appendChild(zoneBadge);
      top.appendChild(headerRow);

      const capacityInfo = el('p', { className: 'text-xs text-slate-500' }, [
        `Capacity: ${rack.capacity || 200} units`,
      ]);
      top.appendChild(capacityInfo);

      // Shelves preview
      const shelvesList = rack.shelves || [];
      const shelvesInfo = el('div', { className: 'pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1' });
      const shelvesLabel = el('div', { className: 'text-[11px] font-semibold text-slate-400' }, [
        `Shelves (${shelvesList.length}):`,
      ]);
      shelvesInfo.appendChild(shelvesLabel);

      if (shelvesList.length > 0) {
        const pills = el('div', { className: 'flex flex-wrap gap-1.5' });
        shelvesList.forEach((s) => {
          const pill = el(
            'span',
            { className: 'px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-700 dark:text-slate-300' },
            [`Shelf ${s.shelf_number || s.id}`]
          );
          pills.appendChild(pill);
        });
        shelvesInfo.appendChild(pills);
      } else {
        shelvesInfo.appendChild(el('div', { className: 'text-[11px] text-slate-400 italic' }, ['No shelves created']));
      }
      top.appendChild(shelvesInfo);

      // Bottom actions
      const bottom = el('div', { className: 'pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2' });
      const viewMedsBtn = el(
        'button',
        { className: 'px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition' },
        ['View Medicines']
      );
      viewMedsBtn.addEventListener('click', () => openMedicinesInRackDrawer(rack));

      const rightBtns = el('div', { className: 'flex items-center gap-1.5' });
      const addShelfBtn = el(
        'button',
        { className: 'px-2.5 py-1 text-[11px] font-semibold rounded-lg text-primary-600 hover:bg-primary-50 transition' },
        ['+ Shelf']
      );
      addShelfBtn.addEventListener('click', () => handleAddShelf(rack.id));

      const delBtn = el(
        'button',
        { className: 'p-1.5 text-slate-400 hover:text-red-600 transition', title: 'Delete Rack' },
        [createTrashIcon()]
      );
      delBtn.addEventListener('click', () => handleDeleteRack(rack.id));

      rightBtns.appendChild(addShelfBtn);
      rightBtns.appendChild(delBtn);

      bottom.appendChild(viewMedsBtn);
      bottom.appendChild(rightBtns);

      card.appendChild(top);
      card.appendChild(bottom);
      container.appendChild(card);
    });
  } catch (err) {
    toast.error('Failed to load storage racks');
  }
}

function openRackModal(rack = null) {
  const modal = document.getElementById('rack-modal');
  const title = document.getElementById('rack-modal-title');
  const form = document.getElementById('form-rack');
  if (!modal || !form) return;

  form.reset();
  if (rack) {
    title.textContent = 'Edit Storage Rack';
    document.getElementById('rack-id').value = rack.id;
    document.getElementById('rack-name').value = rack.name || '';
    document.getElementById('rack-zone').value = rack.zone || '';
    document.getElementById('rack-capacity').value = String(rack.capacity || 200);
  } else {
    title.textContent = 'Add Storage Rack';
    document.getElementById('rack-id').value = '';
    document.getElementById('rack-capacity').value = '200';
  }

  modal.classList.remove('hidden');
}

function closeRackModal() {
  document.getElementById('rack-modal')?.classList.add('hidden');
}

async function handleSaveRack(e) {
  e.preventDefault();
  const id = document.getElementById('rack-id')?.value;
  const payload = {
    name: document.getElementById('rack-name')?.value.trim(),
    zone: document.getElementById('rack-zone')?.value.trim() || 'General',
    capacity: parseInt(document.getElementById('rack-capacity')?.value, 10) || 200,
  };

  try {
    if (id) {
      await api.put(`/admin/racks/${id}`, payload);
      toast.success('Rack updated');
    } else {
      await api.post('/admin/racks', payload);
      toast.success('Rack created');
    }
    closeRackModal();
    loadRacks();
  } catch (err) {
    toast.error(err.message || 'Failed to save rack');
  }
}

async function handleDeleteRack(id) {
  if (!confirm('Are you sure you want to delete this storage rack?')) return;
  try {
    await api.delete(`/admin/racks/${id}`);
    toast.success('Rack deleted');
    loadRacks();
  } catch (err) {
    toast.error(err.message || 'Failed to delete rack');
  }
}

async function handleAddShelf(rackId) {
  const shelfNumber = prompt('Enter Shelf Number / Identifier (e.g. 1, 2, Top):');
  if (!shelfNumber) return;

  try {
    await api.post('/admin/shelves', {
      rack_id: rackId,
      shelf_number: shelfNumber.trim(),
      capacity: 100,
    });
    toast.success('Shelf added to rack');
    loadRacks();
  } catch (err) {
    toast.error(err.message || 'Failed to add shelf');
  }
}

async function openMedicinesInRackDrawer(rack) {
  const drawer = document.getElementById('rack-medicines-drawer');
  const title = document.getElementById('drawer-rack-title');
  const container = document.getElementById('rack-medicines-container');

  if (title) title.textContent = `Medicines in ${rack.name}`;
  if (container) {
    clear(container);
    container.appendChild(el('div', { className: 'text-center py-6 text-slate-400 text-xs' }, ['Loading medicines...']));
  }
  drawer?.classList.remove('hidden');

  try {
    const res = await api.get(`/admin/racks/${rack.id}/medicines`);
    const items = res?.data || [];
    clear(container);

    if (items.length === 0) {
      container.appendChild(
        el('div', { className: 'p-6 text-center text-xs text-slate-400' }, ['No batches currently placed in this rack.'])
      );
      return;
    }

    items.forEach((item) => {
      const card = el('div', { className: 'p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs space-y-1' });
      const top = el('div', { className: 'flex items-center justify-between' });
      const medName = el('span', { className: 'font-bold text-slate-900 dark:text-white' }, [item.medicine_name || 'Unnamed']);
      const qtyBadge = el('span', { className: 'font-semibold text-primary-600' }, [`${item.quantity} Units`]);
      top.appendChild(medName);
      top.appendChild(qtyBadge);

      const sub = el('div', { className: 'flex items-center justify-between text-[11px] text-slate-400' });
      sub.appendChild(el('span', {}, [`Batch: ${item.batch_number || '-'}`]));
      sub.appendChild(el('span', {}, [`Shelf: ${item.shelf_number || item.shelf_id || '-'}`]));

      card.appendChild(top);
      card.appendChild(sub);
      container.appendChild(card);
    });
  } catch (err) {
    clear(container);
    container.appendChild(el('div', { className: 'text-center py-4 text-red-500 text-xs' }, ['Failed to load medicines']));
  }
}

function createTrashIcon() {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'w-4 h-4');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('viewBox', '0 0 24 24');

  const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  p.setAttribute('stroke-linecap', 'round');
  p.setAttribute('stroke-linejoin', 'round');
  p.setAttribute('stroke-width', '2');
  p.setAttribute('d', 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16');
  svg.appendChild(p);

  return svg;
}

