import { api, setTokenProvider } from './api.js';

let accessToken = null;
let currentUser = null;
let initPromise = null;

export const auth = {
  /**
   * Initialize authentication on page load via silent refresh.
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
        await auth.refreshToken();
      } catch (_) {
        accessToken = null;
        currentUser = null;
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
   * Request silent token refresh using HttpOnly cookie.
   */
  async refreshToken() {
    try {
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
   * Log out user, revoking refresh token on server and clearing in-memory token.
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
      window.location.href = '/login.html';
    }
  },
};

