import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

// Mock window and CustomEvent
global.window = {
  events: [],
  dispatchEvent(event) {
    this.events.push(event);
  },
};

global.CustomEvent = class {
  constructor(name, options = {}) {
    this.type = name;
    this.detail = options.detail;
  }
};

const { apiFetch, setTokenProvider, api } = await import('../../src/js/core/api.js');

describe('API Core Fetch Wrapper', () => {
  beforeEach(() => {
    global.window.events = [];
  });

  test('parses success envelope and returns payload data', async () => {
    global.fetch = async (url, options) => {
      return {
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({
          success: true,
          data: { id: 1, name: 'Paracetamol' },
          meta: { version: '1.0.0' },
        }),
      };
    };

    const res = await apiFetch('/medicines/1');
    assert.equal(res.success, true);
    assert.equal(res.data.name, 'Paracetamol');
  });

  test('normalizes API error envelope into Error object', async () => {
    global.fetch = async () => {
      return {
        ok: false,
        status: 422,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({
          success: false,
          error: {
            code: 'VALIDATION_FAILED',
            message: 'Phone is invalid',
            fields: { phone: ['Must be 10 digits'] },
          },
        }),
      };
    };

    await assert.rejects(
      async () => {
        await apiFetch('/auth/otp/request', { method: 'POST', body: '{}' });
      },
      (err) => {
        assert.equal(err.status, 422);
        assert.equal(err.code, 'VALIDATION_FAILED');
        assert.equal(err.message, 'Phone is invalid');
        assert.deepEqual(err.fields, { phone: ['Must be 10 digits'] });
        return true;
      }
    );
  });

  test('handles 401 with one-time silent refresh and retries original request', async () => {
    let callCount = 0;
    let refreshCalled = false;

    setTokenProvider(
      () => (refreshCalled ? 'new_refreshed_jwt' : 'expired_jwt'),
      async () => {
        refreshCalled = true;
        return 'new_refreshed_jwt';
      }
    );

    global.fetch = async (url, options) => {
      callCount++;
      if (callCount === 1) {
        // First call fails with 401
        return {
          ok: false,
          status: 401,
          headers: new Headers({ 'content-type': 'application/json' }),
          json: async () => ({ success: false, error: { code: 'UNAUTHORIZED' } }),
        };
      }
      // Second retry succeeds
      assert.equal(options.headers['Authorization'], 'Bearer new_refreshed_jwt');
      return {
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ success: true, data: { status: 'refreshed_and_authenticated' } }),
      };
    };

    const res = await apiFetch('/orders');
    assert.equal(refreshCalled, true);
    assert.equal(callCount, 2);
    assert.equal(res.data.status, 'refreshed_and_authenticated');
  });

  test('handles 429 by dispatching rate-limit event and throwing RateLimit error', async () => {
    global.fetch = async () => {
      return {
        ok: false,
        status: 429,
        headers: new Headers({
          'content-type': 'application/json',
          'Retry-After': '42',
        }),
        json: async () => ({
          success: false,
          error: {
            code: 'RATE_LIMITED',
            message: 'Too many requests.',
            retry_after: 42,
          },
        }),
      };
    };

    await assert.rejects(
      async () => {
        await apiFetch('/auth/otp/request');
      },
      (err) => {
        assert.equal(err.status, 429);
        assert.equal(err.retryAfter, 42);
        return true;
      }
    );

    assert.equal(global.window.events.length, 1);
    assert.equal(global.window.events[0].type, 'meditrack:rate-limited');
    assert.equal(global.window.events[0].detail.retryAfter, 42);
  });
});

