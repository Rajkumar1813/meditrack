import { el, clear, append } from './dom.js';

export function renderPagination(container, meta, onPageChange) {
  if (!container || !meta) return;
  clear(container);

  if (meta.type === 'offset') {
    const { page, total, per_page, last_page, from, to } = meta;
    if (total === 0) return;

    const info = el('div', { className: 'text-xs text-slate-500 dark:text-slate-400' }, [
      `Showing ${from} to ${to} of ${total}`,
    ]);

    const btnWrapper = el('div', { className: 'flex items-center gap-1.5 text-sm' });

    // Prev Button
    const prevBtn = el(
      'button',
      {
        className: `px-3 py-1.5 rounded-lg border text-xs font-medium transition ${
          page <= 1
            ? 'opacity-40 cursor-not-allowed border-slate-200 dark:border-slate-800'
            : 'hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700'
        }`,
        disabled: page <= 1,
        onClick: () => {
          if (page > 1) onPageChange(page - 1);
        },
      },
      ['Prev']
    );
    btnWrapper.appendChild(prevBtn);

    // Page Numbers (up to 5 pages around current)
    const startPage = Math.max(1, page - 2);
    const endPage = Math.min(last_page, page + 2);

    for (let p = startPage; p <= endPage; p++) {
      const isCurrent = p === page;
      const numBtn = el(
        'button',
        {
          className: `w-8 h-8 rounded-lg text-xs font-medium transition flex items-center justify-center ${
            isCurrent
              ? 'bg-emerald-600 text-white font-semibold shadow-sm'
              : 'hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'
          }`,
          onClick: () => {
            if (!isCurrent) onPageChange(p);
          },
        },
        [String(p)]
      );
      btnWrapper.appendChild(numBtn);
    }

    // Next Button
    const nextBtn = el(
      'button',
      {
        className: `px-3 py-1.5 rounded-lg border text-xs font-medium transition ${
          page >= last_page
            ? 'opacity-40 cursor-not-allowed border-slate-200 dark:border-slate-800'
            : 'hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700'
        }`,
        disabled: page >= last_page,
        onClick: () => {
          if (page < last_page) onPageChange(page + 1);
        },
      },
      ['Next']
    );
    btnWrapper.appendChild(nextBtn);

    const layout = el('div', { className: 'flex flex-wrap items-center justify-between gap-3 w-full py-3' }, [
      info,
      btnWrapper,
    ]);

    append(container, layout);
  }
}

