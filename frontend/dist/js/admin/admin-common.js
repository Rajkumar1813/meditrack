import { auth } from '../core/auth.js';
import { toast } from '../core/toast.js';

export async function initAdminCommon(activeNavKey = '') {
  await auth.init();

  if (!auth.isAuthenticated()) {
    const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `/login.html?returnUrl=${returnUrl}`;
    return null;
  }

  const user = auth.getUser();
  const role = user?.role || 'staff';

  // Role validation
  if (!['admin', 'pharmacist', 'staff'].includes(role)) {
    window.location.href = '/index.html';
    return null;
  }

  // Update topbar profile info
  const initialEl = document.getElementById('admin-user-initial');
  const nameEl = document.getElementById('admin-user-name');
  const roleEl = document.getElementById('admin-user-role');

  if (initialEl) {
    const displayName = user.name || (user.phone ? user.phone : 'Admin');
    initialEl.textContent = displayName.charAt(0).toUpperCase();
  }
  if (nameEl) {
    nameEl.textContent = user.name || (user.phone ? user.phone : 'Staff Member');
  }
  if (roleEl) {
    roleEl.textContent = role === 'admin' ? 'Owner / Admin' : role === 'pharmacist' ? 'Registered Pharmacist' : 'Staff';
  }

  // Role-based navigation filtering:
  // Pharmacist and Staff cannot access admin-only sections (e.g. settings)
  const adminOnlyLinks = document.querySelectorAll('[data-role-required="admin"]');
  adminOnlyLinks.forEach((elem) => {
    if (role !== 'admin') {
      elem.classList.add('hidden');
    }
  });

  // Guard page if page itself requires admin role
  if (document.body.dataset.adminRole === 'admin' && role !== 'admin') {
    toast.error('Access restricted to administrators only.');
    window.location.href = '/admin/index.html';
    return null;
  }

  // Highlight active sidebar item
  if (activeNavKey) {
    const activeLink = document.querySelector(`[data-admin-nav="${activeNavKey}"]`);
    if (activeLink) {
      activeLink.classList.remove('text-slate-700', 'dark:text-slate-200');
      activeLink.classList.add(
        'bg-primary-50',
        'dark:bg-primary-950/40',
        'text-primary-700',
        'dark:text-primary-300',
        'font-semibold',
        'shadow-sm'
      );
    }
  }

  // Topbar global search listener
  const searchInput = document.getElementById('admin-global-search');
  if (searchInput) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = searchInput.value.trim();
        if (query) {
          window.location.href = `/admin/medicines.html?q=${encodeURIComponent(query)}`;
        }
      }
    });
  }

  return user;
}

