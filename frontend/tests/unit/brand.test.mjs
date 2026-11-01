import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

// Pre-define window.BRAND
global.window = {
  BRAND: {
    name: 'MediTrack',
    version: '1.0.0',
    currencySymbol: '₹',
    logo: {
      full: 'assets/brand/logo.svg',
      fullDark: 'assets/brand/logo-dark.svg',
    },
  },
};

const { brand } = await import('../../src/js/core/brand.js');

describe('Brand Core Module', () => {
  test('brand.get retrieves top-level and nested tokens', () => {
    assert.equal(brand.get('name'), 'MediTrack');
    assert.equal(brand.get('currencySymbol'), '₹');
    assert.equal(brand.get('logo.full'), 'assets/brand/logo.svg');
    assert.equal(brand.get('non.existent', 'fallback'), 'fallback');
  });

  test('brand returns whole brand object when no key provided', () => {
    const all = brand.get();
    assert.equal(all.name, 'MediTrack');
    assert.equal(all.version, '1.0.0');
  });
});

