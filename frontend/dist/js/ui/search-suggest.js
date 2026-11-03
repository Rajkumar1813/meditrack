import { api } from '../core/api.js';
import { el, clear, append } from '../core/dom.js';

const RECENT_KEY = 'meditrack_recent_searches';
const MAX_RECENT = 5;

// User-friendly page labels mapping (route/path -> clean label)
export const ROUTE_LABELS = {
  '/': 'Home',
  '/index': 'Home',
  '/catalog': 'Medicine Catalog',
  '/cart': 'Shopping Cart',
  '/checkout': 'Checkout & Payment',
  '/orders': 'My Orders',
  '/order': 'Order Details',
  '/upload-rx': 'Upload Prescription',
  '/profile': 'My Profile',
  '/notifications': 'Notifications',
  '/login': 'Login / Register',
  '/admin': 'Dashboard',
  '/admin/index': 'Dashboard',
  '/admin/medicines': 'Medicines & Inventory',
  '/admin/expiring': 'Expiring Soon',
  '/admin/expired': 'Expired Inventory',
  '/admin/racks': 'Racks & Shelves',
  '/admin/orders': 'Orders Management',
  '/admin/prescriptions': 'Prescription Verification',
  '/admin/customers': 'Customers',
  '/admin/invoices': 'Invoices',
  '/admin/settings': 'Settings',
};

// Searchable user-facing pages
export const USER_PAGES = [
  { cleanUrl: '/catalog', label: 'Medicine Catalog', subtitle: 'Browse all medicines & salts', keywords: ['catalog', 'medicines', 'tablets', 'products', 'browse', 'shop', 'all medicines'] },
  { cleanUrl: '/cart', label: 'Shopping Cart', subtitle: 'Review selected items', keywords: ['cart', 'basket', 'bag', 'checkout'] },
  { cleanUrl: '/orders', label: 'My Orders', subtitle: 'Order history & live tracking', keywords: ['orders', 'my orders', 'tracking', 'history', 'invoice'] },
  { cleanUrl: '/upload-rx', label: 'Upload Prescription', subtitle: 'Direct pharmacist verification', keywords: ['prescription', 'upload', 'rx', 'doctor', 'slip', 'rx upload'] },
  { cleanUrl: '/profile', label: 'My Profile', subtitle: 'Addresses & account settings', keywords: ['profile', 'account', 'addresses', 'refills'] },
  { cleanUrl: '/notifications', label: 'Notifications', subtitle: 'Order alerts & refill reminders', keywords: ['notifications', 'alerts', 'reminders'] },
  { cleanUrl: '/admin', label: 'Dashboard', subtitle: 'Store KPI stats & overview', keywords: ['dashboard', 'admin', 'kpi', 'reports'] },
  { cleanUrl: '/admin/medicines', label: 'Medicines & Inventory', subtitle: 'Stock, batches, and price management', keywords: ['inventory', 'medicines', 'stock', 'batches', 'add medicine'] },
  { cleanUrl: '/admin/orders', label: 'Orders Management', subtitle: 'Dispatch & fulfillment pipeline', keywords: ['admin orders', 'dispatch', 'packing', 'shipping'] },
  { cleanUrl: '/admin/prescriptions', label: 'Prescription Queue', subtitle: 'Pharmacist verification & stamp', keywords: ['review rx', 'prescriptions', 'verify rx'] },
  { cleanUrl: '/admin/racks', label: 'Racks & Shelves', subtitle: 'Physical storage locations', keywords: ['racks', 'shelves', 'storage'] },
  { cleanUrl: '/admin/invoices', label: 'Invoices', subtitle: 'GST tax bills & printouts', keywords: ['invoices', 'gst', 'tax billing', 'bills'] },
  { cleanUrl: '/admin/settings', label: 'Settings', subtitle: 'Pharmacy store details & configuration', keywords: ['settings', 'store configuration'] },
];

// Blocklist for internal files/paths that must NEVER appear in search
export const INTERNAL_PATHS = [
  'config', 'includes', 'api', 'vendor', 'backend', 'storage', 'tests',
  'infra', 'partials', 'scripts', 'node_modules', '.git', '.env', 'database',
  'bin', 'dist', 'composer', 'phpunit', 'build'
];

/**
 * Clean & format any raw title, route, or search term:
 * 1. Strips extensions (.html, .htm, .php, .js, .css, etc.)
 * 2. Checks ROUTE_LABELS mapping for clean exact display
 * 3. Replaces underscores and hyphens with spaces
 * 4. Converts to Title Case
 */
export function formatSearchTitle(rawText) {
  if (!rawText || typeof rawText !== 'string') return '';
  const trimmed = rawText.trim();

  // Strip query string or hash
  const pathOnly = trimmed.split('?')[0].split('#')[0];

  // Strip extensions (.html, .htm, .php, .js, .css, .json, .sql, etc.)
  const noExt = pathOnly.replace(/\.(html?|php|jsx?|tsx?|css|json|sql|md|txt)$/i, '');

  // Check route mapping (with leading slash or stripped)
  const normalizedRoute = noExt.startsWith('/') ? noExt : `/${noExt}`;
  if (ROUTE_LABELS[normalizedRoute]) {
    return ROUTE_LABELS[normalizedRoute];
  }
  if (ROUTE_LABELS[noExt]) {
    return ROUTE_LABELS[noExt];
  }

  // Extract basename if path like /admin/medicines -> medicines
  const baseName = noExt.split('/').filter(Boolean).pop() || noExt;

  // Replace underscores and hyphens with single spaces
  const spaced = baseName.replace(/[-_.]+/g, ' ').trim();

  // Convert to Title Case
  return spaced.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substring(1).toLowerCase());
}

/**
 * Check if a term or path points to internal files or sensitive directories
 */
export function isInternalPath(text) {
  if (!text || typeof text !== 'string') return false;
  const lower = text.toLowerCase().trim();

  // Direct match or directory traversal
  return INTERNAL_PATHS.some((p) => {
    return (
      lower === p ||
      lower.startsWith(`${p}/`) ||
      lower.includes(`/${p}/`) ||
      lower.startsWith(`${p}.`) ||
      lower.endsWith(`.${p}`) ||
      lower.includes(`.${p}`)
    );
  });
}

export function getRecentSearches() {
  try {
    const raw = localStorage.getItem(RECENT_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed)
      ? parsed.filter((item) => typeof item === 'string' && !isInternalPath(item))
      : [];
  } catch (_) {
    return [];
  }
}

export function saveRecentSearch(query) {
  if (!query || typeof query !== 'string') return;
  const q = formatSearchTitle(query.trim());
  if (q.length < 2 || isInternalPath(q)) return;

  try {
    let list = getRecentSearches().filter((item) => item.toLowerCase() !== q.toLowerCase());
    list.unshift(q);
    if (list.length > MAX_RECENT) list = list.slice(0, MAX_RECENT);
    localStorage.setItem(RECENT_KEY, JSON.stringify(list));
  } catch (_) {}
}

export function clearRecentSearches() {
  try {
    localStorage.removeItem(RECENT_KEY);
  } catch (_) {}
}

/**
 * Safely creates text fragments with matching substrings highlighted in a <mark> element.
 * Zero innerHTML used - 100% XSS safe.
 */
function createHighlightedText(textStr, queryStr) {
  const container = document.createDocumentFragment();
  if (!textStr) return container;
  if (!queryStr || queryStr.trim().length === 0) {
    container.appendChild(document.createTextNode(textStr));
    return container;
  }

  const lowerText = textStr.toLowerCase();
  const lowerQuery = queryStr.toLowerCase().trim();
  let startIndex = 0;

  while (startIndex < textStr.length) {
    const matchIndex = lowerText.indexOf(lowerQuery, startIndex);
    if (matchIndex === -1) {
      container.appendChild(document.createTextNode(textStr.slice(startIndex)));
      break;
    }

    if (matchIndex > startIndex) {
      container.appendChild(document.createTextNode(textStr.slice(startIndex, matchIndex)));
    }

    const mark = document.createElement('mark');
    mark.className = 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-semibold px-0.5 rounded';
    mark.appendChild(document.createTextNode(textStr.slice(matchIndex, matchIndex + lowerQuery.length)));
    container.appendChild(mark);

    startIndex = matchIndex + lowerQuery.length;
  }

  return container;
}

/**
 * Attach auto-suggest behavior to a search input.
 */
export function initSearchSuggest(inputEl, dropdownEl) {
  if (!inputEl || !dropdownEl) return;

  let activeIndex = -1;
  let currentItems = [];
  let abortCtrl = null;
  let debounceTimer = null;

  function closeDropdown() {
    dropdownEl.classList.add('hidden');
    clear(dropdownEl);
    activeIndex = -1;
    currentItems = [];
  }

  function renderRecent() {
    clear(dropdownEl);
    activeIndex = -1;
    currentItems = [];

    const recents = getRecentSearches();
    if (recents.length === 0) {
      dropdownEl.classList.add('hidden');
      return;
    }

    const header = el('div', { className: 'px-4 py-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between border-b border-slate-100 dark:border-slate-800' }, [
      el('span', {}, ['Recent Searches']),
      el('button', {
        className: 'text-xs text-slate-400 hover:text-red-500 transition',
        onClick: (e) => {
          e.preventDefault();
          e.stopPropagation();
          clearRecentSearches();
          closeDropdown();
        },
      }, ['Clear All']),
    ]);
    append(dropdownEl, header);

    const listContainer = el('div', { className: 'py-1' });

    recents.forEach((itemText, idx) => {
      const cleanLabel = formatSearchTitle(itemText);
      const itemRow = el('div', {
        className: 'px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between transition',
        dataset: { index: idx },
        onClick: () => {
          inputEl.value = cleanLabel;
          saveRecentSearch(cleanLabel);
          closeDropdown();
          window.location.href = `/catalog?q=${encodeURIComponent(cleanLabel)}`;
        },
      }, [
        el('div', { className: 'flex items-center gap-2.5' }, [
          el('span', { className: 'text-slate-400' }, ['🕒']),
          el('span', {}, [cleanLabel]),
        ]),
      ]);

      currentItems.push({
        element: itemRow,
        action: () => {
          inputEl.value = cleanLabel;
          saveRecentSearch(cleanLabel);
          closeDropdown();
          window.location.href = `/catalog?q=${encodeURIComponent(cleanLabel)}`;
        },
      });

      append(listContainer, itemRow);
    });

    append(dropdownEl, listContainer);
    dropdownEl.classList.remove('hidden');
  }

  function renderResults(query, results) {
    clear(dropdownEl);
    activeIndex = -1;
    currentItems = [];

    const cleanQuery = formatSearchTitle(query);
    const qLower = query.toLowerCase().trim();

    // 1. Match User-Facing Pages
    const matchingPages = USER_PAGES.filter((p) => {
      if (p.label.toLowerCase().includes(qLower)) return true;
      return p.keywords.some((k) => k.includes(qLower) || qLower.includes(k));
    }).slice(0, 3);

    // 2. Filter Database results (medicines & salts)
    const validDbResults = (results || []).filter((r) => {
      const title = r.name || r.title || '';
      return !isInternalPath(title);
    });

    const medicines = validDbResults.filter((r) => r.type === 'medicine');
    const salts = validDbResults.filter((r) => r.type === 'salt');

    const totalResults = matchingPages.length + medicines.length + salts.length;

    if (totalResults === 0) {
      const emptyRow = el('div', { className: 'px-4 py-4 text-center text-xs text-slate-500 dark:text-slate-400' }, [
        `No suggestions found for "${cleanQuery}"`,
      ]);
      append(dropdownEl, emptyRow);
      dropdownEl.classList.remove('hidden');
      return;
    }

    // A. User-Facing Pages Group
    if (matchingPages.length > 0) {
      append(dropdownEl, el('div', { className: 'px-4 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50 dark:bg-slate-800/50' }, ['Pages & Navigation']));
      for (const page of matchingPages) {
        const itemIdx = currentItems.length;
        const row = el('div', {
          className: 'px-4 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800/50 transition',
          dataset: { index: itemIdx },
          onClick: () => {
            saveRecentSearch(page.label);
            closeDropdown();
            window.location.href = page.cleanUrl;
          },
        });

        const leftCol = el('div', { className: 'flex-1 min-w-0' });
        const titleEl = el('div', { className: 'text-sm font-semibold text-slate-900 dark:text-slate-100 truncate' });
        titleEl.appendChild(createHighlightedText(page.label, query));
        append(leftCol, titleEl);

        const subEl = el('div', { className: 'text-xs text-slate-400 truncate' }, [page.subtitle]);
        append(leftCol, subEl);

        append(row, leftCol);

        const pill = el('span', { className: 'px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }, ['Page']);
        append(row, pill);

        currentItems.push({
          element: row,
          action: () => {
            saveRecentSearch(page.label);
            closeDropdown();
            window.location.href = page.cleanUrl;
          },
        });

        append(dropdownEl, row);
      }
    }

    // B. Medicines Group
    if (medicines.length > 0) {
      append(dropdownEl, el('div', { className: 'px-4 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50 dark:bg-slate-800/50' }, ['Medicines']));
      for (const med of medicines) {
        const itemIdx = currentItems.length;
        const cleanName = formatSearchTitle(med.name || med.title);
        const row = el('div', {
          className: 'px-4 py-2.5 hover:bg-emerald-50 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800/50 last:border-0 transition',
          dataset: { index: itemIdx },
          onClick: () => {
            saveRecentSearch(cleanName);
            closeDropdown();
            const targetUrl = med.slug ? `/medicine?slug=${encodeURIComponent(med.slug)}` : `/catalog?q=${encodeURIComponent(cleanName)}`;
            window.location.href = targetUrl;
          },
        });

        const leftCol = el('div', { className: 'flex-1 min-w-0' });
        const titleEl = el('div', { className: 'text-sm font-medium text-slate-800 dark:text-slate-100 truncate' });
        titleEl.appendChild(createHighlightedText(cleanName, query));
        append(leftCol, titleEl);

        if (med.generic_name || med.subtitle) {
          const cleanSub = formatSearchTitle(med.generic_name || med.subtitle);
          const subEl = el('div', { className: 'text-xs text-slate-500 dark:text-slate-400 truncate' });
          subEl.appendChild(createHighlightedText(cleanSub, query));
          append(leftCol, subEl);
        }
        append(row, leftCol);

        if (med.requires_rx) {
          const rxBadge = el('span', { className: 'px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shrink-0' }, ['Rx']);
          append(row, rxBadge);
        }

        currentItems.push({
          element: row,
          action: () => {
            saveRecentSearch(cleanName);
            closeDropdown();
            window.location.href = med.slug ? `/medicine?slug=${encodeURIComponent(med.slug)}` : `/catalog?q=${encodeURIComponent(cleanName)}`;
          },
        });

        append(dropdownEl, row);
      }
    }

    // C. Salts Group
    if (salts.length > 0) {
      append(dropdownEl, el('div', { className: 'px-4 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50 dark:bg-slate-800/50' }, ['Composition Salts']));
      for (const salt of salts) {
        const itemIdx = currentItems.length;
        const saltName = formatSearchTitle(salt.name || salt.title);
        const row = el('div', {
          className: 'px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/50 last:border-0 transition',
          dataset: { index: itemIdx },
          onClick: () => {
            saveRecentSearch(saltName);
            closeDropdown();
            window.location.href = `/catalog?q=${encodeURIComponent(saltName)}`;
          },
        });

        const titleEl = el('div', { className: 'text-xs font-semibold text-emerald-700 dark:text-emerald-400' });
        titleEl.appendChild(createHighlightedText(saltName, query));
        append(row, titleEl);

        const label = el('span', { className: 'text-[10px] text-slate-400' }, ['Composition']);
        append(row, label);

        currentItems.push({
          element: row,
          action: () => {
            saveRecentSearch(saltName);
            closeDropdown();
            window.location.href = `/catalog?q=${encodeURIComponent(saltName)}`;
          },
        });

        append(dropdownEl, row);
      }
    }

    // Direct search footer
    const footerLink = el('div', {
      className: 'px-4 py-2.5 text-center text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-slate-50 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer transition',
      onClick: () => {
        saveRecentSearch(cleanQuery);
        closeDropdown();
        window.location.href = `/catalog?q=${encodeURIComponent(cleanQuery)}`;
      },
    }, [`See all results for "${cleanQuery}" →`]);
    append(dropdownEl, footerLink);

    dropdownEl.classList.remove('hidden');
  }

  async function performSearch(query) {
    if (abortCtrl) {
      abortCtrl.abort();
    }
    abortCtrl = new AbortController();

    // Prevent searching internal files
    if (isInternalPath(query)) {
      renderResults(query, []);
      return;
    }

    try {
      const resp = await api.get(`/search/suggest?q=${encodeURIComponent(query)}`, {
        signal: abortCtrl.signal,
      });
      const data = resp?.data || [];
      renderResults(query, data);
    } catch (err) {
      if (err.name === 'AbortError') return;
      // Even if API fails, still render matching user-facing pages
      renderResults(query, []);
    }
  }

  // Input listeners
  inputEl.addEventListener('focus', () => {
    const val = inputEl.value.trim();
    if (val.length >= 2) {
      performSearch(val);
    } else {
      renderRecent();
    }
  });

  inputEl.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const val = inputEl.value.trim();

    if (val.length < 2) {
      if (val.length === 0) {
        renderRecent();
      } else {
        closeDropdown();
      }
      return;
    }

    debounceTimer = setTimeout(() => {
      performSearch(val);
    }, 250);
  });

  // Keyboard Navigation: ArrowUp, ArrowDown, Enter, Escape
  inputEl.addEventListener('keydown', (e) => {
    if (dropdownEl.classList.contains('hidden') || currentItems.length === 0) {
      if (e.key === 'Enter') {
        const val = inputEl.value.trim();
        if (val) {
          const clean = formatSearchTitle(val);
          saveRecentSearch(clean);
          window.location.href = `/catalog?q=${encodeURIComponent(clean)}`;
        }
      }
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % currentItems.length;
      updateActiveItem();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = activeIndex <= 0 ? currentItems.length - 1 : activeIndex - 1;
      updateActiveItem();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIndex >= 0 && activeIndex < currentItems.length) {
        currentItems[activeIndex].action();
      } else {
        const val = inputEl.value.trim();
        if (val) {
          const clean = formatSearchTitle(val);
          saveRecentSearch(clean);
          closeDropdown();
          window.location.href = `/catalog?q=${encodeURIComponent(clean)}`;
        }
      }
    } else if (e.key === 'Escape') {
      closeDropdown();
    }
  });

  function updateActiveItem() {
    currentItems.forEach((item, idx) => {
      if (idx === activeIndex) {
        item.element.classList.add('bg-slate-100', 'dark:bg-slate-800');
        item.element.scrollIntoView({ block: 'nearest' });
      } else {
        item.element.classList.remove('bg-slate-100', 'dark:bg-slate-800');
      }
    });
  }

  // Click outside to close
  document.addEventListener('click', (e) => {
    if (!inputEl.contains(e.target) && !dropdownEl.contains(e.target)) {
      closeDropdown();
    }
  });
}
