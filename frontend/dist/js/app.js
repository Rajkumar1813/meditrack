import { theme } from './core/theme.js';
import { brand } from './core/brand.js';
import { i18n } from './core/i18n.js';
import { guard } from './core/guard.js';
import { auth } from './core/auth.js';
import { toast } from './core/toast.js';
import { clear, el } from './core/dom.js';

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

  // 5. Setup Theme Toggle Listeners
  const themeBtns = document.querySelectorAll('#theme-toggle, #admin-theme-toggle');
  themeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      theme.toggle();
    });
  });

  // 6. Setup Language Toggle Listeners
  const langBtns = document.querySelectorAll('#lang-toggle, #admin-lang-toggle');
  langBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      i18n.toggleLocale();
    });
  });

  // 7. Setup Logout Listeners
  const logoutBtns = document.querySelectorAll('#logout-btn, #admin-logout-btn');
  logoutBtns.forEach((btn) => {
    btn.addEventListener('click', async () => {
      await auth.logout();
    });
  });

  // 8. Update Year in footer
  const yearEl = document.getElementById('current-year');
  if (yearEl) {
    yearEl.textContent = String(new Date().getFullYear());
  }

  // 9. Auth status updates in Header
  window.addEventListener('meditrack:auth-changed', (e) => {
    const user = e.detail?.user;
    const authNavContainer = document.getElementById('auth-nav-container');
    if (authNavContainer) {
      if (user) {
        clear(authNavContainer);
        const userPill = el(
          'a',
          {
            href: ['admin', 'pharmacist', 'staff'].includes(user.role) ? '/admin/index.html' : '/profile.html',
            className:
              'px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-200 transition',
          },
          [user.name || (user.phone ? user.phone : 'Account')]
        );
        authNavContainer.appendChild(userPill);
      }
    }
  });
});
