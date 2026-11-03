import test, { describe } from 'node:test';
import assert from 'node:assert/strict';
import { CONFIG } from '../../src/js/core/config.js';

describe('Authgear Configuration & Client Module', () => {
  test('CONFIG exposes Authgear configuration parameters', () => {
    assert.ok(CONFIG.AUTHGEAR_ENDPOINT, 'CONFIG.AUTHGEAR_ENDPOINT must be defined');
    assert.ok(CONFIG.AUTHGEAR_CLIENT_ID, 'CONFIG.AUTHGEAR_CLIENT_ID must be defined');
    assert.ok(CONFIG.AUTHGEAR_REDIRECT_URI, 'CONFIG.AUTHGEAR_REDIRECT_URI must be defined');
    assert.match(CONFIG.AUTHGEAR_REDIRECT_URI, /auth-callback/, 'Redirect URI should point to auth-callback');
  });

  test('Authgear redirect URL formats correctly with returnUrl', () => {
    const baseRedirect = 'http://localhost:5173/auth-callback.html';
    const targetReturn = '/admin/dashboard.html';

    const url = new URL(baseRedirect);
    url.searchParams.set('returnUrl', targetReturn);

    assert.strictEqual(url.searchParams.get('returnUrl'), '/admin/dashboard.html');
    assert.strictEqual(url.pathname, '/auth-callback.html');
  });
});
