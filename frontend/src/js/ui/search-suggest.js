import { api } from '../core/api.js';
import { el, clear, append } from '../core/dom.js';

const RECENT_KEY = 'meditrack_recent_searches';
const MAX_RECENT = 5;

export function getRecentSearches() {
  try {
    const raw = localStorage.getItem(RECENT_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch (_) {
    return [];
  }
}

export function saveRecentSearch(query) {
  if (!query || typeof query !== 'string') return;
  const q = query.trim();
  if (q.length < 2) return;

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
 * Zero innerHTML used.
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
      const itemRow = el('div', {
        className: 'px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between transition',
        dataset: { index: idx },
        onClick: () => {
          inputEl.value = itemText;
          saveRecentSearch(itemText);
          closeDropdown();
          window.location.href = `/catalog.html?q=${encodeURIComponent(itemText)}`;
        },
      }, [
        el('div', { className: 'flex items-center gap-2.5' }, [
          el('span', { className: 'text-slate-400' }, ['🕒']),
          el('span', {}, [itemText]),
        ]),
      ]);

      currentItems.push({
        element: itemRow,
        action: () => {
          inputEl.value = itemText;
          saveRecentSearch(itemText);
          closeDropdown();
          window.location.href = `/catalog.html?q=${encodeURIComponent(itemText)}`;
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

    if (!results || results.length === 0) {
      const emptyRow = el('div', { className: 'px-4 py-4 text-center text-xs text-slate-500 dark:text-slate-400' }, [
        `No suggestions found for "${query}"`,
      ]);
      append(dropdownEl, emptyRow);
      dropdownEl.classList.remove('hidden');
      return;
    }

    const medicines = results.filter((r) => r.type === 'medicine');
    const salts = results.filter((r) => r.type === 'salt');

    // Medicine Group
    if (medicines.length > 0) {
      append(dropdownEl, el('div', { className: 'px-4 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50 dark:bg-slate-800/50' }, ['Medicines']));
      for (const med of medicines) {
        const itemIdx = currentItems.length;
        const row = el('div', {
          className: 'px-4 py-2.5 hover:bg-emerald-50 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800/50 last:border-0 transition',
          dataset: { index: itemIdx },
          onClick: () => {
            saveRecentSearch(med.name || med.title || query);
            closeDropdown();
            const targetUrl = med.slug ? `/medicine.html?slug=${encodeURIComponent(med.slug)}` : `/catalog.html?q=${encodeURIComponent(med.name || med.title)}`;
            window.location.href = targetUrl;
          },
        });

        const leftCol = el('div', { className: 'flex-1 min-w-0' });
        const titleEl = el('div', { className: 'text-sm font-medium text-slate-800 dark:text-slate-100 truncate' });
        titleEl.appendChild(createHighlightedText(med.name || med.title, query));
        append(leftCol, titleEl);

        if (med.generic_name || med.subtitle) {
          const subEl = el('div', { className: 'text-xs text-slate-500 dark:text-slate-400 truncate' });
          subEl.appendChild(createHighlightedText(med.generic_name || med.subtitle, query));
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
            saveRecentSearch(med.name || med.title || query);
            closeDropdown();
            window.location.href = med.slug ? `/medicine.html?slug=${encodeURIComponent(med.slug)}` : `/catalog.html?q=${encodeURIComponent(med.name || med.title)}`;
          },
        });

        append(dropdownEl, row);
      }
    }

    // Salts Group
    if (salts.length > 0) {
      append(dropdownEl, el('div', { className: 'px-4 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50 dark:bg-slate-800/50' }, ['Composition Salts']));
      for (const salt of salts) {
        const itemIdx = currentItems.length;
        const saltName = salt.name || salt.title;
        const row = el('div', {
          className: 'px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/50 last:border-0 transition',
          dataset: { index: itemIdx },
          onClick: () => {
            saveRecentSearch(saltName);
            closeDropdown();
            window.location.href = `/catalog.html?q=${encodeURIComponent(saltName)}`;
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
            window.location.href = `/catalog.html?q=${encodeURIComponent(saltName)}`;
          },
        });

        append(dropdownEl, row);
      }
    }

    // Direct search footer
    const footerLink = el('div', {
      className: 'px-4 py-2.5 text-center text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-slate-50 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer transition',
      onClick: () => {
        saveRecentSearch(query);
        closeDropdown();
        window.location.href = `/catalog.html?q=${encodeURIComponent(query)}`;
      },
    }, [`See all results for "${query}" →`]);
    append(dropdownEl, footerLink);

    dropdownEl.classList.remove('hidden');
  }

  async function performSearch(query) {
    if (abortCtrl) {
      abortCtrl.abort();
    }
    abortCtrl = new AbortController();

    try {
      const resp = await api.get(`/search/suggest?q=${encodeURIComponent(query)}`, {
        signal: abortCtrl.signal,
      });
      const data = resp?.data || [];
      renderResults(query, data);
    } catch (err) {
      if (err.name === 'AbortError') return;
      closeDropdown();
    }
  }

  // Input event listeners
  inputEl.addEventListener('input', (e) => {
    const val = e.target.value.trim();
    clearTimeout(debounceTimer);

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
    }, 280);
  });

  inputEl.addEventListener('focus', () => {
    const val = inputEl.value.trim();
    if (val.length === 0) {
      renderRecent();
    } else if (val.length >= 2) {
      performSearch(val);
    }
  });

  // Keyboard navigation
  inputEl.addEventListener('keydown', (e) => {
    if (dropdownEl.classList.contains('hidden') || currentItems.length === 0) {
      if (e.key === 'Enter') {
        const val = inputEl.value.trim();
        if (val) {
          saveRecentSearch(val);
          window.location.href = `/catalog.html?q=${encodeURIComponent(val)}`;
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
      activeIndex = (activeIndex - 1 + currentItems.length) % currentItems.length;
      updateActiveItem();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIndex >= 0 && activeIndex < currentItems.length) {
        currentItems[activeIndex].action();
      } else {
        const val = inputEl.value.trim();
        if (val) {
          saveRecentSearch(val);
          window.location.href = `/catalog.html?q=${encodeURIComponent(val)}`;
        }
      }
    } else if (e.key === 'Escape') {
      closeDropdown();
    }
  });

  function updateActiveItem() {
    currentItems.forEach((item, idx) => {
      if (idx === activeIndex) {
        item.element.classList.add('bg-emerald-100', 'dark:bg-slate-700');
        item.element.scrollIntoView({ block: 'nearest' });
      } else {
        item.element.classList.remove('bg-emerald-100', 'dark:bg-slate-700');
      }
    });
  }

  // Close dropdown on outside click
  document.addEventListener('click', (e) => {
    if (!inputEl.contains(e.target) && !dropdownEl.contains(e.target)) {
      closeDropdown();
    }
  });
}

