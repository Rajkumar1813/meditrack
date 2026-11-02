import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { createMedicineCard, createSkeletonCard } from '../ui/components.js';
import { init3DTilt } from '../ui/tilt-3d.js';

let currentFilters = {
  q: '',
  category: '',
  requires_rx: '',
  in_stock: '1',
  sort: 'name_asc',
  page: 1,
  limit: 12,
};

let currentView = 'grid'; // 'grid' or 'list'

document.addEventListener('DOMContentLoaded', async () => {
  const gridContainer = document.getElementById('catalog-grid');
  const countIndicator = document.getElementById('catalog-count-indicator');
  const prevBtn = document.getElementById('pagination-prev');
  const nextBtn = document.getElementById('pagination-next');
  const pageIndicator = document.getElementById('pagination-page-indicator');
  const searchInput = document.getElementById('catalog-search-input');
  const categorySelect = document.getElementById('filter-category');
  const mobileCategorySelect = document.getElementById('mobile-filter-category');
  const rxSelect = document.getElementById('filter-rx');
  const stockCheck = document.getElementById('filter-stock');
  const sortSelect = document.getElementById('catalog-sort-select');
  const gridViewBtn = document.getElementById('view-toggle-grid');
  const listViewBtn = document.getElementById('view-toggle-list');
  const filterDrawer = document.getElementById('filter-drawer');
  const openFilterDrawerBtn = document.getElementById('open-filters-btn');
  const closeFilterDrawerBtn = document.getElementById('close-filters-btn');
  const applyDrawerBtn = document.getElementById('apply-drawer-filters-btn');
  const clearFiltersBtn = document.getElementById('clear-filters-btn');

  // 1. Read URL query parameters
  const params = new URLSearchParams(window.location.search);
  if (params.has('q')) currentFilters.q = params.get('q');
  if (params.has('category')) currentFilters.category = params.get('category');
  if (params.has('requires_rx')) currentFilters.requires_rx = params.get('requires_rx');
  if (params.has('in_stock')) currentFilters.in_stock = params.get('in_stock');
  if (params.has('sort')) currentFilters.sort = params.get('sort');
  if (params.has('page')) currentFilters.page = parseInt(params.get('page'), 10) || 1;

  // Sync inputs with URL state
  if (searchInput) searchInput.value = currentFilters.q;
  if (stockCheck) stockCheck.checked = currentFilters.in_stock === '1';
  if (sortSelect) sortSelect.value = currentFilters.sort;
  if (rxSelect) rxSelect.value = currentFilters.requires_rx;

  // 2. Fetch categories for filters dropdown
  try {
    const catResp = await api.get('/categories');
    const categories = catResp?.data || [];
    [categorySelect, mobileCategorySelect].forEach((sel) => {
      if (!sel) return;
      categories.forEach((cat) => {
        const opt = el('option', { value: cat.slug || cat.id }, [cat.name]);
        if (currentFilters.category === cat.slug || currentFilters.category === String(cat.id)) {
          opt.selected = true;
        }
        sel.appendChild(opt);
      });
    });
  } catch (_) {}

  // 3. Load medicines
  async function loadMedicines() {
    if (!gridContainer) return;

    // Render Skeletons
    clear(gridContainer);
    for (let i = 0; i < 6; i++) {
      gridContainer.appendChild(createSkeletonCard());
    }

    const queryParams = new URLSearchParams();
    if (currentFilters.q) queryParams.set('q', currentFilters.q);
    if (currentFilters.category) queryParams.set('category', currentFilters.category);
    if (currentFilters.requires_rx !== '') queryParams.set('requires_rx', currentFilters.requires_rx);
    if (currentFilters.in_stock !== '') queryParams.set('in_stock', currentFilters.in_stock);
    if (currentFilters.sort) queryParams.set('sort', currentFilters.sort);
    queryParams.set('page', String(currentFilters.page));
    queryParams.set('limit', String(currentFilters.limit));

    // Update browser URL silently
    const newRelativePathQuery = window.location.pathname + '?' + queryParams.toString();
    history.replaceState(null, '', newRelativePathQuery);

    try {
      const resp = await api.get(`/medicines?${queryParams.toString()}`);
      const medicines = resp?.data || [];
      const meta = resp?.meta || {};

      clear(gridContainer);

      if (medicines.length === 0) {
        const emptyState = el(
          'div',
          { className: 'col-span-full py-16 text-center flex flex-col items-center justify-center' },
          [
            el('div', { className: 'w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-2xl mb-4' }, ['🔍']),
            el('h3', { className: 'text-base font-bold text-slate-800 dark:text-slate-200 mb-1' }, ['No medicines found']),
            el('p', { className: 'text-xs text-slate-500 dark:text-slate-400 max-w-sm mb-4' }, ['Try adjusting your filters, searching for an alternative generic name, or checking spelling.']),
            el(
              'button',
              {
                className: 'px-4 py-2 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition',
                onClick: () => {
                  currentFilters = { q: '', category: '', requires_rx: '', in_stock: '1', sort: 'name_asc', page: 1, limit: 12 };
                  if (searchInput) searchInput.value = '';
                  if (categorySelect) categorySelect.value = '';
                  if (rxSelect) rxSelect.value = '';
                  if (stockCheck) stockCheck.checked = true;
                  loadMedicines();
                },
              },
              ['Clear Filters']
            ),
          ]
        );
        append(gridContainer, emptyState);
        if (countIndicator) setText(countIndicator, '0 medicines found');
      } else {
        medicines.forEach((med) => {
          gridContainer.appendChild(createMedicineCard(med));
        });
        init3DTilt(gridContainer);

        const total = meta.total || medicines.length;
        const from = (currentFilters.page - 1) * currentFilters.limit + 1;
        const to = Math.min(total, from + medicines.length - 1);
        if (countIndicator) {
          setText(countIndicator, `Showing ${from}–${to} of ${total} medicines`);
        }
      }

      // Update Pagination state
      const totalPages = Math.ceil((meta.total || 1) / currentFilters.limit) || 1;
      if (pageIndicator) {
        setText(pageIndicator, `Page ${currentFilters.page} of ${totalPages}`);
      }
      if (prevBtn) {
        prevBtn.disabled = currentFilters.page <= 1;
      }
      if (nextBtn) {
        nextBtn.disabled = currentFilters.page >= totalPages;
      }
    } catch (err) {
      clear(gridContainer);
      gridContainer.appendChild(
        el('div', { className: 'col-span-full py-16 text-center text-red-500 text-sm' }, [
          err.message || 'Unable to load catalog.',
        ])
      );
    }
  }

  // 4. Attach event listeners
  if (searchInput) {
    let timer = null;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        currentFilters.q = e.target.value.trim();
        currentFilters.page = 1;
        loadMedicines();
      }, 350);
    });
  }

  if (categorySelect) {
    categorySelect.addEventListener('change', (e) => {
      currentFilters.category = e.target.value;
      currentFilters.page = 1;
      loadMedicines();
    });
  }

  if (rxSelect) {
    rxSelect.addEventListener('change', (e) => {
      currentFilters.requires_rx = e.target.value;
      currentFilters.page = 1;
      loadMedicines();
    });
  }

  if (stockCheck) {
    stockCheck.addEventListener('change', (e) => {
      currentFilters.in_stock = e.target.checked ? '1' : '';
      currentFilters.page = 1;
      loadMedicines();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      currentFilters.sort = e.target.value;
      currentFilters.page = 1;
      loadMedicines();
    });
  }

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', () => {
      currentFilters = { q: '', category: '', requires_rx: '', in_stock: '1', sort: 'name_asc', page: 1, limit: 12 };
      if (searchInput) searchInput.value = '';
      if (categorySelect) categorySelect.value = '';
      if (rxSelect) rxSelect.value = '';
      if (stockCheck) stockCheck.checked = true;
      loadMedicines();
    });
  }

  // View toggle
  if (gridViewBtn && listViewBtn) {
    gridViewBtn.addEventListener('click', () => {
      currentView = 'grid';
      gridViewBtn.classList.add('bg-white', 'dark:bg-slate-800', 'text-primary-600', 'shadow-sm');
      gridViewBtn.classList.remove('text-slate-400');
      listViewBtn.classList.remove('bg-white', 'dark:bg-slate-800', 'text-primary-600', 'shadow-sm');
      listViewBtn.classList.add('text-slate-400');
      gridContainer.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6';
    });

    listViewBtn.addEventListener('click', () => {
      currentView = 'list';
      listViewBtn.classList.add('bg-white', 'dark:bg-slate-800', 'text-primary-600', 'shadow-sm');
      listViewBtn.classList.remove('text-slate-400');
      gridViewBtn.classList.remove('bg-white', 'dark:bg-slate-800', 'text-primary-600', 'shadow-sm');
      gridViewBtn.classList.add('text-slate-400');
      gridContainer.className = 'grid grid-cols-1 gap-3';
    });
  }

  // Pagination Controls
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentFilters.page > 1) {
        currentFilters.page--;
        loadMedicines();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentFilters.page++;
      loadMedicines();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Mobile Filter Drawer
  if (openFilterDrawerBtn && filterDrawer) {
    openFilterDrawerBtn.addEventListener('click', () => {
      if (typeof filterDrawer.showModal === 'function') {
        filterDrawer.showModal();
      } else {
        filterDrawer.classList.remove('hidden');
      }
    });
  }

  if (closeFilterDrawerBtn && filterDrawer) {
    closeFilterDrawerBtn.addEventListener('click', () => {
      if (typeof filterDrawer.close === 'function') {
        filterDrawer.close();
      } else {
        filterDrawer.classList.add('hidden');
      }
    });
  }

  if (applyDrawerBtn && filterDrawer) {
    applyDrawerBtn.addEventListener('click', () => {
      if (mobileCategorySelect) currentFilters.category = mobileCategorySelect.value;
      const mobileRx = document.getElementById('mobile-filter-rx');
      if (mobileRx) currentFilters.requires_rx = mobileRx.value;
      const mobileStock = document.getElementById('mobile-filter-stock');
      if (mobileStock) currentFilters.in_stock = mobileStock.checked ? '1' : '';

      currentFilters.page = 1;
      if (typeof filterDrawer.close === 'function') filterDrawer.close();
      else filterDrawer.classList.add('hidden');

      loadMedicines();
    });
  }

  // Initial fetch
  await loadMedicines();
});

