import { api, setTokenProvider } from './api.js';
import { authgearClient } from './authgear.js';

let accessToken = null;
let currentUser = null;
let initPromise = null;

export const auth = {
  /**
   * Initialize authentication on page load via Authgear SDK & backend sync.
   */
  async init() {
    if (initPromise) {
      return initPromise;
    }

    initPromise = (async () => {
      setTokenProvider(
        () => accessToken,
        () => auth.refreshToken()
      );

      try {
        await authgearClient.init();
        if (authgearClient.isAuthenticated()) {
          accessToken = await authgearClient.getAccessToken();
          if (accessToken) {
            try {
              const resp = await api.get('/me', {
                headers: { Authorization: `Bearer ${accessToken}` },
              });
              currentUser = resp?.data || null;
            } catch (_) {
              // Try syncing with backend
              const syncResp = await api.post(
                '/auth/authgear/sync',
                { access_token: accessToken },
                { headers: { Authorization: `Bearer ${accessToken}` } }
              );
              currentUser = syncResp?.data?.user || null;
            }
          }
        } else {
          // Fallback to cookie-based refresh if available
          await auth.refreshToken();
        }
      } catch (_) {
        accessToken = null;
        currentUser = null;
      }

      if (typeof window !== 'undefined' && currentUser) {
        window.dispatchEvent(new CustomEvent('meditrack:auth-changed', { detail: { user: currentUser } }));
      }

      return currentUser;
    })();

    return initPromise;
  },

  getToken() {
    return accessToken;
  },

  getUser() {
    return currentUser;
  },

  isAuthenticated() {
    return Boolean(accessToken && currentUser);
  },

  hasRole(role) {
    if (!currentUser || !currentUser.role) return false;
    if (Array.isArray(role)) {
      return role.includes(currentUser.role);
    }
    return currentUser.role === role;
  },

  setSession(token, user) {
    accessToken = token;
    currentUser = user;
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('meditrack:auth-changed', { detail: { user } }));
    }
  },

  /**
   * Request silent token refresh using Authgear or HttpOnly cookie.
   */
  async refreshToken() {
    try {
      const authgearToken = await authgearClient.getAccessToken();
      if (authgearToken) {
        accessToken = authgearToken;
        if (!currentUser) {
          const resp = await api.get('/me', {
            headers: { Authorization: `Bearer ${accessToken}` },
          });
          currentUser = resp?.data || null;
        }
        return accessToken;
      }

      const response = await api.post('/auth/refresh', {}, { _retry: true });
      const token = response?.data?.tokens?.access_token || response?.data?.access_token;
      if (token) {
        accessToken = token;
        currentUser = response.data.user || currentUser;
        window.dispatchEvent(new CustomEvent('meditrack:auth-changed', { detail: { user: currentUser } }));
        return accessToken;
      }
    } catch (err) {
      accessToken = null;
      currentUser = null;
      throw err;
    }
    return null;
  },

  /**
   * Trigger Authgear login flow
   */
  async login(returnUrl) {
    return authgearClient.login(returnUrl);
  },

  /**
   * Log out user from Authgear and server session
   */
  async logout() {
    try {
      await api.post('/auth/logout', {});
    } catch (_) {}

    accessToken = null;
    currentUser = null;
    initPromise = null;

    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('meditrack:auth-changed', { detail: { user: null } }));
    }

    await authgearClient.logout(`${window.location.origin}/login.html`);
  },
};
