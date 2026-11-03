/**
 * Global Configuration for MediTrack Frontend
 */
export const CONFIG = {
  API_BASE_URL: '/api/v1',
  DEFAULT_TIMEOUT_MS: 15000,
  SEARCH_DEBOUNCE_MS: 250,
  MAX_UPLOAD_SIZE_BYTES: 5 * 1024 * 1024, // 5 MB

  // Authgear OIDC / PKCE Configuration (Synchronized dynamically with backend/.env)
  AUTHGEAR_ENDPOINT:
    (typeof window !== 'undefined' && window.__AUTHGEAR_ENDPOINT__) ||
    'https://maid-ola765.authgear.cloud',
  AUTHGEAR_CLIENT_ID:
    (typeof window !== 'undefined' && window.__AUTHGEAR_CLIENT_ID__) ||
    '05212c8d267b49e7',
  AUTHGEAR_REDIRECT_URI:
    (typeof window !== 'undefined' && window.__AUTHGEAR_REDIRECT_URI__) ||
    (typeof window !== 'undefined' && window.location?.origin
      ? `${window.location.origin}/auth-callback`
      : 'http://localhost:5173/auth-callback'),
};
