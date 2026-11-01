import { el, append } from './dom.js';

let container = null;

function getContainer() {
  if (!container && typeof document !== 'undefined') {
    container = document.getElementById('toast-container');
    if (!container) {
      container = el('div', {
        id: 'toast-container',
        className: 'fixed bottom-5 right-5 z-[100] flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4',
      });
      document.body.appendChild(container);
    }
  }
  return container;
}

export const toast = {
  show(message, type = 'info', durationMs = 4000) {
    const cont = getContainer();
    if (!cont) return;

    const typeStyles = {
      success: 'bg-emerald-600 text-white border-emerald-700',
      error: 'bg-red-600 text-white border-red-700',
      warning: 'bg-amber-500 text-white border-amber-600',
      info: 'bg-slate-800 text-white border-slate-700 dark:bg-slate-700',
    };

    const style = typeStyles[type] || typeStyles.info;

    const item = el(
      'div',
      {
        className: `pointer-events-auto p-3.5 rounded-xl border shadow-lg flex items-center justify-between gap-3 text-sm font-medium transition-all transform duration-300 translate-y-2 opacity-0 ${style}`,
      },
      [
        el('span', { className: 'flex-1' }, [message]),
        el(
          'button',
          {
            className: 'opacity-70 hover:opacity-100 text-white font-bold ml-2 text-base leading-none',
            onClick: () => removeToast(item),
          },
          ['×']
        ),
      ]
    );

    append(cont, item);

    requestAnimationFrame(() => {
      item.classList.remove('translate-y-2', 'opacity-0');
      item.classList.add('translate-y-0', 'opacity-100');
    });

    const timer = setTimeout(() => {
      removeToast(item);
    }, durationMs);

    function removeToast(node) {
      clearTimeout(timer);
      node.classList.remove('translate-y-0', 'opacity-100');
      node.classList.add('translate-y-2', 'opacity-0');
      setTimeout(() => {
        if (node.parentNode) {
          node.parentNode.removeChild(node);
        }
      }, 300);
    }
  },

  success(msg) {
    this.show(msg, 'success');
  },
  error(msg) {
    this.show(msg, 'error');
  },
  warning(msg) {
    this.show(msg, 'warning');
  },
  info(msg) {
    this.show(msg, 'info');
  },
};

// Global listener for rate limit events
if (typeof window !== 'undefined') {
  window.addEventListener('meditrack:rate-limited', (e) => {
    const { retryAfter, message } = e.detail;
    toast.warning(`${message} (${retryAfter}s remaining)`);
  });
}

