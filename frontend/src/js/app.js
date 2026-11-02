import { theme } from './core/theme.js';
import { brand } from './core/brand.js';
import { i18n } from './core/i18n.js';
import { guard } from './core/guard.js';
import { auth } from './core/auth.js';
import { toast } from './core/toast.js';
import { clear, el } from './core/dom.js';
import { api } from './core/api.js';
import { initSearchSuggest } from './ui/search-suggest.js';
import { init3DTilt } from './ui/tilt-3d.js';

// Global application bootstrap
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Initialize Theme
  theme.init();

  // 2. Apply Brand & Trigger async background refresh
  brand.apply();
  brand.refresh();

  // 3. Initialize Translations
  await i18n.init();

  // 4. Run Route Guard
  await guard.check();

  // 5. Initialize Global Search Auto-Suggest
  const globalSearchInput = document.getElementById('global-search');
  const globalSearchDropdown = document.getElementById('global-search-dropdown');
  if (globalSearchInput && globalSearchDropdown) {
    initSearchSuggest(globalSearchInput, globalSearchDropdown);
  }

  // 6. Initialize 3D Card Tilt
  init3DTilt();

  // 7. Setup Theme Toggle Listeners
  const themeBtns = document.querySelectorAll('#theme-toggle, #admin-theme-toggle');
  themeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      theme.toggle();
    });
  });

  // 8. Setup Language Toggle Listeners
  const langBtns = document.querySelectorAll('#lang-toggle, #admin-lang-toggle');
  langBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      i18n.toggleLocale();
    });
  });

  // 9. Setup Logout Listeners
  const logoutBtns = document.querySelectorAll('#logout-btn, #admin-logout-btn');
  logoutBtns.forEach((btn) => {
    btn.addEventListener('click', async () => {
      await auth.logout();
    });
  });

  // 10. Update Year in footer
  const yearEl = document.getElementById('current-year');
  if (yearEl) {
    yearEl.textContent = String(new Date().getFullYear());
  }

  // 11. Highlight active navigation in bottom-nav
  const path = window.location.pathname;
  const navLinks = document.querySelectorAll('nav [data-nav]');
  navLinks.forEach((link) => {
    const navKey = link.dataset.nav;
    if (
      (navKey === 'home' && (path === '/' || path === '/index.html')) ||
      (navKey === 'catalog' && path.includes('catalog.html')) ||
      (navKey === 'cart' && path.includes('cart.html')) ||
      (navKey === 'orders' && path.includes('order')) ||
      (navKey === 'profile' && path.includes('profile.html'))
    ) {
      link.classList.add('text-primary-600', 'dark:text-primary-400', 'font-semibold');
      link.classList.remove('text-slate-600', 'dark:text-slate-400');
    }
  });

  // 12. Helper function to update Cart and Notification Badges
  async function refreshBadges() {
    if (!auth.isAuthenticated()) return;

    // Cart Badge
    try {
      const cartResp = await api.get('/cart');
      const cartData = cartResp?.data;
      const count = (cartData?.items || []).reduce((sum, item) => sum + (item.quantity || 1), 0);
      const badge = document.getElementById('header-cart-badge');
      if (badge) {
        if (count > 0) {
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.classList.remove('hidden');
        } else {
          badge.classList.add('hidden');
        }
      }
    } catch (_) {}

    // Notifications Badge
    try {
      const notifResp = await api.get('/notifications?unread=1');
      const unreadCount = notifResp?.meta?.unread_count ?? notifResp?.data?.length ?? 0;
      const notifBadge = document.getElementById('header-notif-badge');
      if (notifBadge) {
        if (unreadCount > 0) {
          notifBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
          notifBadge.classList.remove('hidden');
        } else {
          notifBadge.classList.add('hidden');
        }
      }
    } catch (_) {}
  }

  // 13. Auth status updates in Header
  window.addEventListener('meditrack:auth-changed', (e) => {
    const user = e.detail?.user;
    const authNavContainer = document.getElementById('auth-nav-container');
    if (authNavContainer) {
      clear(authNavContainer);
      if (user) {
        const userPill = el(
          'a',
          {
            href: ['admin', 'pharmacist', 'staff'].includes(user.role) ? '/admin/index.html' : '/profile.html',
            className:
              'px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center gap-1.5',
          },
          [
            el('span', { className: 'w-2 h-2 rounded-full bg-emerald-500 inline-block' }),
            user.name || (user.phone ? user.phone : 'Account'),
          ]
        );
        authNavContainer.appendChild(userPill);
        refreshBadges();
      } else {
        const loginBtn = el(
          'a',
          {
            href: '/login.html',
            className:
              'px-4 py-1.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition',
            dataset: { i18n: 'login' },
          },
          ['Login']
        );
        authNavContainer.appendChild(loginBtn);
      }
    }
  });

  window.addEventListener('meditrack:cart-updated', () => {
    refreshBadges();
  });

  window.addEventListener('meditrack:notifications-updated', () => {
    refreshBadges();
  });

  // Initial badge refresh if already logged in
  if (auth.isAuthenticated()) {
    refreshBadges();
  }
});
