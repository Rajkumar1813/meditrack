import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { createStatusBadge } from '../ui/components.js';
import { init3DTilt } from '../ui/tilt-3d.js';
import { toast } from '../core/toast.js';

import { auth } from '../core/auth.js';

let nextCursor = null;
let isLoadingMore = false;

document.addEventListener('DOMContentLoaded', async () => {
  await auth.init();

  const ordersContainer = document.getElementById('orders-list-container');
  const emptyState = document.getElementById('orders-empty-state');
  const loadMoreBtn = document.getElementById('orders-load-more-btn');

  async function fetchOrders(cursor = null) {
    if (!ordersContainer) return;

    try {
      const endpoint = cursor ? `/orders?cursor=${encodeURIComponent(cursor)}&limit=10` : '/orders?limit=10';
      const resp = await api.get(endpoint);
      const orders = resp?.data || [];
      const meta = resp?.meta || {};

      nextCursor = meta.next_cursor || null;

      if (!cursor) {
        clear(ordersContainer);
      }

      if (orders.length === 0 && !cursor) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (loadMoreBtn) loadMoreBtn.classList.add('hidden');
        return;
      }

      if (emptyState) emptyState.classList.add('hidden');

      orders.forEach((order) => {
        const orderCard = renderOrderCard(order);
        ordersContainer.appendChild(orderCard);
      });

      init3DTilt(ordersContainer);

      if (loadMoreBtn) {
        if (nextCursor) {
          loadMoreBtn.classList.remove('hidden');
        } else {
          loadMoreBtn.classList.add('hidden');
        }
      }
    } catch (err) {
      if (!cursor) {
        clear(ordersContainer);
        ordersContainer.appendChild(
          el('div', { className: 'py-16 text-center text-red-500 text-sm' }, [
            err.message || 'Unable to load orders at this time.',
          ])
        );
      } else {
        toast.error(err.message || 'Failed to load more orders');
      }
    }
  }

  function renderOrderCard(order) {
    const card = el(
      'article',
      {
        className:
          'card-3d bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-5 sm:p-6 shadow-soft flex flex-col justify-between transition',
        dataset: { tilt: 'true' },
      },
      [
        // Top row: Order Number, Date, Status Badge
        el('div', { className: 'flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800' }, [
          el('div', {}, [
            el('div', { className: 'flex items-center gap-2' }, [
              el('span', { className: 'text-xs font-semibold text-slate-400' }, ['Order']),
              el('span', { className: 'text-sm font-extrabold text-slate-900 dark:text-white' }, [
                `#${order.order_no || order.id}`,
              ]),
            ]),
            el('span', { className: 'text-[11px] text-slate-400' }, [
              order.created_at ? new Date(order.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '',
            ]),
          ]),
          createStatusBadge(order.status),
        ]),

        // Middle: Items preview
        el('div', { className: 'py-4 flex-1' }, [
          el(
            'ul',
            { className: 'space-y-1.5' },
            (order.items || []).slice(0, 3).map((item) =>
              el('li', { className: 'text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between' }, [
                el('span', { className: 'truncate max-w-[70%]' }, [
                  `${item.name || item.medicine_name || 'Item'} × ${item.quantity}`,
                ]),
                el('span', { className: 'font-medium' }, [
                  `₹${Number(item.line_total || item.unit_price * item.quantity || 0).toFixed(2)}`,
                ]),
              ])
            )
          ),
          (order.items || []).length > 3
            ? el('p', { className: 'text-[11px] text-slate-400 mt-2 italic' }, [
                `+ ${(order.items || []).length - 3} more items...`,
              ])
            : null,
        ]),

        // Bottom row: Total & Actions
        el('div', { className: 'pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3' }, [
          el('div', {}, [
            el('span', { className: 'text-[11px] text-slate-400 block' }, ['Total Payable']),
            el('span', { className: 'text-base font-extrabold text-slate-900 dark:text-white' }, [
              `₹${Number(order.total || 0).toFixed(2)}`,
            ]),
          ]),
          el('div', { className: 'flex items-center gap-2' }, [
            el(
              'button',
              {
                type: 'button',
                className:
                  'px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition',
                onClick: async () => {
                  try {
                    await api.post(`/orders/${order.id}/reorder`, {});
                    toast.success('Items added to cart!');
                    window.location.href = '/cart.html';
                  } catch (e) {
                    toast.error(e.message || 'Failed to reorder items');
                  }
                },
              },
              ['1-Tap Reorder']
            ),
            el(
              'a',
              {
                href: `/order.html?id=${order.id}`,
                className:
                  'px-4 py-2 rounded-xl text-xs font-bold bg-primary-600 hover:bg-primary-700 text-white shadow-soft transition',
              },
              ['View Details →']
            ),
          ]),
        ]),
      ]
    );

    return card;
  }

  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', async () => {
      if (nextCursor && !isLoadingMore) {
        isLoadingMore = true;
        setText(loadMoreBtn, 'Loading...');
        await fetchOrders(nextCursor);
        setText(loadMoreBtn, 'Load More Orders');
        isLoadingMore = false;
      }
    });
  }

  await fetchOrders();
});

