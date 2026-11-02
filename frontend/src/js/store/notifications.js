import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';

import { auth } from '../core/auth.js';

let currentFilterUnread = false;
let nextCursor = null;

document.addEventListener('DOMContentLoaded', async () => {
  await auth.init();

  const container = document.getElementById('notifications-list-container');
  const emptyState = document.getElementById('notifications-empty-state');
  const markAllBtn = document.getElementById('mark-all-read-btn');
  const filterAllBtn = document.getElementById('notif-filter-all');
  const filterUnreadBtn = document.getElementById('notif-filter-unread');
  const loadMoreBtn = document.getElementById('notif-load-more-btn');

  async function fetchNotifications(cursor = null) {
    if (!container) return;

    try {
      let url = `/notifications?limit=15`;
      if (currentFilterUnread) url += '&unread=1';
      if (cursor) url += `&cursor=${encodeURIComponent(cursor)}`;

      const resp = await api.get(url);
      const items = resp?.data || [];
      const meta = resp?.meta || {};

      nextCursor = meta.next_cursor || null;

      if (!cursor) {
        clear(container);
      }

      if (items.length === 0 && !cursor) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (loadMoreBtn) loadMoreBtn.classList.add('hidden');
        return;
      }

      if (emptyState) emptyState.classList.add('hidden');

      items.forEach((notif) => {
        const itemRow = renderNotificationRow(notif);
        container.appendChild(itemRow);
      });

      if (loadMoreBtn) {
        if (nextCursor) loadMoreBtn.classList.remove('hidden');
        else loadMoreBtn.classList.add('hidden');
      }

      window.dispatchEvent(new CustomEvent('meditrack:notifications-updated'));
    } catch (err) {
      if (!cursor) {
        clear(container);
        container.appendChild(
          el('div', { className: 'py-12 text-center text-red-500 text-xs' }, [
            err.message || 'Failed to load notifications.',
          ])
        );
      }
    }
  }

  function renderNotificationRow(notif) {
    const isUnread = !notif.read_at;

    const row = el(
      'div',
      {
        className: `p-4 sm:p-5 rounded-2xl border transition cursor-pointer flex items-start gap-4 ${
          isUnread
            ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60 shadow-sm'
            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800'
        }`,
        onClick: async () => {
          // Mark single notification read
          if (isUnread) {
            try {
              await api.post('/notifications/read', { notification_id: notif.id });
              row.classList.remove('bg-emerald-50/50', 'dark:bg-emerald-950/20', 'border-emerald-200');
              row.classList.add('bg-white', 'dark:bg-slate-900', 'border-slate-200');
              const dot = row.querySelector('.unread-dot');
              if (dot) dot.remove();
              window.dispatchEvent(new CustomEvent('meditrack:notifications-updated'));
            } catch (_) {}
          }

          // Follow deep link if present in notification payload
          const link = notif.data?.link || notif.link;
          if (link) {
            window.location.href = link;
          }
        },
      },
      [
        // Icon
        el(
          'div',
          {
            className: `w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-lg ${
              isUnread ? 'bg-primary-600 text-white shadow-soft' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'
            }`,
          },
          [getIconForType(notif.type)]
        ),

        // Body
        el('div', { className: 'flex-1 min-w-0' }, [
          el('div', { className: 'flex items-center justify-between gap-2 mb-1' }, [
            el('h4', { className: 'text-sm font-bold text-slate-900 dark:text-white truncate' }, [
              notif.title || 'Notification',
            ]),
            el('span', { className: 'text-[11px] text-slate-400 shrink-0' }, [
              notif.created_at ? new Date(notif.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '',
            ]),
          ]),
          el('p', { className: 'text-xs text-slate-600 dark:text-slate-300 leading-relaxed' }, [
            notif.body || notif.message || '',
          ]),
        ]),

        // Unread Dot
        isUnread
          ? el('span', { className: 'unread-dot w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0 self-center' })
          : null,
      ]
    );

    return row;
  }

  function getIconForType(type) {
    const t = String(type || '').toLowerCase();
    if (t.includes('order')) return '📦';
    if (t.includes('refill')) return '💊';
    if (t.includes('rx') || t.includes('prescription')) return '📋';
    if (t.includes('stock')) return '⚠️';
    return '🔔';
  }

  if (markAllBtn) {
    markAllBtn.addEventListener('click', async () => {
      try {
        await api.post('/notifications/read', { all: true });
        toast.success('All notifications marked as read');
        await fetchNotifications();
      } catch (e) {
        toast.error(e.message || 'Failed to mark notifications read');
      }
    });
  }

  if (filterAllBtn && filterUnreadBtn) {
    filterAllBtn.addEventListener('click', () => {
      currentFilterUnread = false;
      filterAllBtn.className = 'px-3.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 text-primary-600 dark:text-primary-400 font-bold text-xs shadow-sm';
      filterUnreadBtn.className = 'px-3.5 py-1.5 rounded-xl text-slate-500 hover:text-slate-700 font-medium text-xs';
      fetchNotifications();
    });

    filterUnreadBtn.addEventListener('click', () => {
      currentFilterUnread = true;
      filterUnreadBtn.className = 'px-3.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 text-primary-600 dark:text-primary-400 font-bold text-xs shadow-sm';
      filterAllBtn.className = 'px-3.5 py-1.5 rounded-xl text-slate-500 hover:text-slate-700 font-medium text-xs';
      fetchNotifications();
    });
  }

  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', () => {
      if (nextCursor) fetchNotifications(nextCursor);
    });
  }

  await fetchNotifications();
});

