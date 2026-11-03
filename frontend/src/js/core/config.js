/**
 * Global Configuration for MediTrack Frontend
 */
export const CONFIG = {
  API_BASE_URL: '/api/v1',
  DEFAULT_TIMEOUT_MS: 15000,
  SEARCH_DEBOUNCE_MS: 250,
  MAX_UPLOAD_SIZE_BYTES: 5 * 1024 * 1024, // 5 MB

  // Authgear OIDC / PKCE Configuration
  AUTHGEAR_ENDPOINT:
    (typeof window !== 'undefined' && window.__AUTHGEAR_ENDPOINT__) ||
    'https://meditrack-dev.authgear.cloud',
  AUTHGEAR_CLIENT_ID:
    (typeof window !== 'undefined' && window.__AUTHGEAR_CLIENT_ID__) ||
    'meditrack_web_spa',
  AUTHGEAR_REDIRECT_URI:
    (typeof window !== 'undefined' && window.__AUTHGEAR_REDIRECT_URI__) ||
    (typeof window !== 'undefined' && window.location?.origin
      ? `${window.location.origin}/auth-callback.html`
      : 'http://localhost:5173/auth-callback.html'),
};
