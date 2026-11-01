import { brand } from './brand.js';

const STORAGE_KEY = 'meditrack_theme';

export const theme = {
  init() {
    if (typeof window === 'undefined') return;

    const saved = localStorage.getItem(STORAGE_KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }

    brand.apply();
  },

  isDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
  },

  toggle() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
    brand.apply();
    window.dispatchEvent(new CustomEvent('meditrack:theme-changed', { detail: { isDark } }));
    return isDark;
  },

  set(mode) {
    if (mode === 'dark') {
      document.documentElement.classList.add('dark');
      localStorage.setItem(STORAGE_KEY, 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem(STORAGE_KEY, 'light');
    }
    brand.apply();
  },
};

