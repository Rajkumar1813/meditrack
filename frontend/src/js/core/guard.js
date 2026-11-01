import { auth } from './auth.js';

export const guard = {
  /**
   * Run route guard based on document.body dataset rules.
   */
  async check() {
    await auth.init();

    const body = document.body;
    if (!body) return;

    const guardRule = body.dataset.guard; // e.g. "auth", "admin", "guest"
    const allowedRolesAttr = body.dataset.roles; // e.g. "admin,pharmacist"

    const isAuth = auth.isAuthenticated();
    const user = auth.getUser();
    const currentPath = window.location.pathname;

    // Guest only (e.g. login.html)
    if (guardRule === 'guest') {
      if (isAuth) {
        if (['admin', 'pharmacist', 'staff'].includes(user?.role)) {
          window.location.href = '/admin/index.html';
        } else {
          window.location.href = '/index.html';
        }
      }
      return;
    }

    // Require Auth
    if (guardRule === 'auth' || allowedRolesAttr) {
      if (!isAuth) {
        const returnUrl = encodeURIComponent(currentPath + window.location.search);
        window.location.href = `/login.html?returnUrl=${returnUrl}`;
        return;
      }
    }

    // Check specific role requirements
    if (allowedRolesAttr) {
      const allowedRoles = allowedRolesAttr.split(',').map((r) => r.trim());
      if (!allowedRoles.includes(user?.role)) {
        // Forbidden: redirect customer to store or show error
        window.location.href = '/index.html';
      }
    }
  },
};

