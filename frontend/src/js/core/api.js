import { CONFIG } from './config.js';

let tokenGetter = null;
let tokenRefresher = null;
let isRefreshing = false;
let refreshSubscribers = [];

export function setTokenProvider(getter, refresher) {
  tokenGetter = getter;
  tokenRefresher = refresher;
}

function onRefreshed(newToken) {
  refreshSubscribers.forEach((callback) => callback(newToken));
  refreshSubscribers = [];
}

function addRefreshSubscriber(callback) {
  refreshSubscribers.push(callback);
}

/**
 * Enhanced fetch wrapper for MediTrack JSON API.
 * Handles envelopes, token injection, silent 401 refresh, 429 retry-after events, and timeouts.
 */
export async function apiFetch(endpoint, options = {}) {
  const url = endpoint.startsWith('http')
    ? endpoint
    : `${CONFIG.API_BASE_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;

  const timeoutMs = options.timeout || CONFIG.DEFAULT_TIMEOUT_MS;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  const headers = {
    Accept: 'application/json',
    ...(options.headers || {}),
  };

  // Attach Content-Type for JSON body if not form data
  if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  // Inject Bearer token from memory if available
  const token = tokenGetter ? tokenGetter() : null;
  if (token && !headers['Authorization']) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  // Include credentials for HttpOnly refresh cookie
  const fetchOptions = {
    ...options,
    headers,
    signal: controller.signal,
    credentials: options.credentials || 'include',
  };

  try {
    const response = await fetch(url, fetchOptions);
    clearTimeout(timer);

    // Handle 401 Unauthorized (attempt one-time silent refresh)
    if (response.status === 401 && !options._retry && tokenRefresher) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          addRefreshSubscriber((newToken) => {
            options._retry = true;
            if (newToken) {
              options.headers = { ...(options.headers || {}), Authorization: `Bearer ${newToken}` };
            }
            apiFetch(endpoint, options).then(resolve).catch(reject);
          });
        });
      }

      options._retry = true;
      isRefreshing = true;

      try {
        const refreshedToken = await tokenRefresher();
        isRefreshing = false;
        onRefreshed(refreshedToken);

        if (refreshedToken) {
          options.headers = { ...(options.headers || {}), Authorization: `Bearer ${refreshedToken}` };
          return apiFetch(endpoint, options);
        }
      } catch (refreshErr) {
        isRefreshing = false;
        onRefreshed(null);
        throw refreshErr;
      }
    }

    // Handle 429 Rate Limited
    if (response.status === 429) {
      let retryAfter = parseInt(response.headers.get('Retry-After') || '60', 10);
      let errorPayload = null;

      try {
        errorPayload = await response.json();
        if (errorPayload?.error?.retry_after) {
          retryAfter = errorPayload.error.retry_after;
        }
      } catch (_) {}

      // Emit custom rate-limit event for global UI countdown toast
      if (typeof window !== 'undefined') {
        window.dispatchEvent(
          new CustomEvent('meditrack:rate-limited', {
            detail: {
              retryAfter,
              message: errorPayload?.error?.message || `Rate limit exceeded. Try again in ${retryAfter}s.`,
            },
          })
        );
      }

      const rateLimitError = new Error(
        errorPayload?.error?.message || `Too many requests. Please wait ${retryAfter} seconds.`
      );
      rateLimitError.status = 429;
      rateLimitError.code = 'RATE_LIMITED';
      rateLimitError.retryAfter = retryAfter;
      throw rateLimitError;
    }

    // Handle 304 Not Modified
    if (response.status === 304) {
      return { status: 304, notModified: true };
    }

    // Parse JSON Envelope
    const contentType = response.headers.get('content-type') || '';
    let payload = null;
    if (contentType.includes('application/json')) {
      payload = await response.json();
    } else {
      payload = await response.text();
    }

    if (!response.ok) {
      const err = new Error(payload?.error?.message || `HTTP ${response.status} Error`);
      err.status = response.status;
      err.code = payload?.error?.code || 'HTTP_ERROR';
      err.fields = payload?.error?.fields || null;
      err.payload = payload;
      throw err;
    }

    return payload;
  } catch (err) {
    clearTimeout(timer);
    if (err.name === 'AbortError') {
      const timeoutErr = new Error(`Request timed out after ${timeoutMs}ms.`);
      timeoutErr.code = 'TIMEOUT';
      throw timeoutErr;
    }
    throw err;
  }
}

export const api = {
  get: (endpoint, options) => apiFetch(endpoint, { ...options, method: 'GET' }),
  post: (endpoint, body, options) =>
    apiFetch(endpoint, {
      ...options,
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  put: (endpoint, body, options) =>
    apiFetch(endpoint, {
      ...options,
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  patch: (endpoint, body, options) =>
    apiFetch(endpoint, {
      ...options,
      method: 'PATCH',
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  delete: (endpoint, options) => apiFetch(endpoint, { ...options, method: 'DELETE' }),
};

