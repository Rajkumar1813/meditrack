import { api } from './api.js';

let activeBrand = (typeof window !== 'undefined' && window.BRAND) ? { ...window.BRAND } : {};

export const brand = {
  get(key, defaultValue = null) {
    if (!key) return activeBrand;
    const parts = key.split('.');
    let cur = activeBrand;
    for (const part of parts) {
      if (cur === null || cur === undefined || typeof cur !== 'object') {
        return defaultValue;
      }
      cur = cur[part];
    }
    return cur !== undefined ? cur : defaultValue;
  },

  /**
   * Apply branding tokens to DOM elements.
   */
  apply() {
    if (typeof document === 'undefined') return;

    // Replace text elements with [data-brand="field"]
    const brandElements = document.querySelectorAll('[data-brand]');
    brandElements.forEach((el) => {
      const field = el.dataset.brand;
      const val = brand.get(field);
      if (val !== null && val !== undefined) {
        el.textContent = String(val);
      }
    });

    // Update logos with [data-brand-logo]
    const isDark = document.documentElement.classList.contains('dark');
    const logoElements = document.querySelectorAll('img[data-brand-logo]');
    logoElements.forEach((img) => {
      const type = img.dataset.brandLogo || 'full';
      if (type === 'mark') {
        img.src = '/' + (activeBrand.logo?.mark || 'assets/brand/logo-mark.svg');
      } else {
        const logoSrc = isDark
          ? activeBrand.logo?.fullDark || activeBrand.logo?.full || 'assets/brand/logo-dark.svg'
          : activeBrand.logo?.full || 'assets/brand/logo.svg';
        img.src = '/' + logoSrc;
      }
      img.alt = activeBrand.name || 'Logo';
    });
  },

  /**
   * Refresh brand asynchronously from API in background without blocking render.
   */
  async refresh() {
    try {
      const response = await api.get('/brand');
      if (response && response.data) {
        activeBrand = {
          ...activeBrand,
          ...response.data.app,
          logo: response.data.logo || activeBrand.logo,
          version: response.meta?.version || response.data.version,
        };
        brand.apply();
      }
    } catch (_) {
      // Offline fallback: keep pre-compiled window.BRAND
    }
  },
};

