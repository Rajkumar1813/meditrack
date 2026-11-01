const STORAGE_KEY = 'meditrack_locale';
let currentLocale = 'en';
let dictionaries = {
  en: {},
  hi: {},
};

export const i18n = {
  async init() {
    if (typeof localStorage !== 'undefined') {
      currentLocale = localStorage.getItem(STORAGE_KEY) || 'en';
    }

    try {
      const [enRes, hiRes] = await Promise.all([
        fetch('/lang/en.json').then((r) => r.json()),
        fetch('/lang/hi.json').then((r) => r.json()),
      ]);
      dictionaries.en = enRes;
      dictionaries.hi = hiRes;
    } catch (_) {
      // Fallback
    }

    i18n.apply();
  },

  getLocale() {
    return currentLocale;
  },

  async setLocale(locale) {
    if (locale !== 'en' && locale !== 'hi') return;
    currentLocale = locale;
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, locale);
    }
    if (typeof document !== 'undefined') {
      document.documentElement.lang = locale;
    }
    i18n.apply();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('meditrack:locale-changed', { detail: { locale } }));
    }
  },

  toggleLocale() {
    return i18n.setLocale(currentLocale === 'en' ? 'hi' : 'en');
  },

  t(key, replace = {}) {
    const dict = dictionaries[currentLocale] || dictionaries.en;
    let translation = dict[key] || dictionaries.en[key] || key;

    for (const [k, v] of Object.entries(replace)) {
      translation = translation.replace(new RegExp(`:${k}|\\{${k}\\}`, 'g'), String(v));
    }
    return translation;
  },

  apply() {
    if (typeof document === 'undefined') return;

    // Text content
    document.querySelectorAll('[data-i18n]').forEach((el) => {
      const key = el.dataset.i18n;
      const translation = i18n.t(key);
      if (translation) {
        el.textContent = translation;
      }
    });

    // Placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
      const key = el.dataset.i18nPlaceholder;
      const translation = i18n.t(key);
      if (translation) {
        el.placeholder = translation;
      }
    });

    // Update locale indicator buttons if present
    document.querySelectorAll('[data-i18n-active]').forEach((el) => {
      el.textContent = currentLocale === 'en' ? 'हिन्दी' : 'English';
    });
  },
};

