import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

// Mock localStorage
const storage = {};
global.localStorage = {
  getItem: (key) => storage[key] || null,
  setItem: (key, val) => { storage[key] = String(val); },
};

global.window = {
  dispatchEvent: () => {},
};

global.CustomEvent = class {
  constructor(name, options = {}) {
    this.type = name;
    this.detail = options.detail;
  }
};

const { i18n } = await import('../../src/js/core/i18n.js');

describe('i18n Core Module', () => {
  test('default locale is en and stores change in localStorage', async () => {
    assert.equal(i18n.getLocale(), 'en');

    await i18n.setLocale('hi');
    assert.equal(i18n.getLocale(), 'hi');
    assert.equal(global.localStorage.getItem('meditrack_locale'), 'hi');

    await i18n.setLocale('en');
    assert.equal(i18n.getLocale(), 'en');
  });

  test('toggleLocale switches between en and hi', async () => {
    await i18n.setLocale('en');
    await i18n.toggleLocale();
    assert.equal(i18n.getLocale(), 'hi');
    await i18n.toggleLocale();
    assert.equal(i18n.getLocale(), 'en');
  });
});

