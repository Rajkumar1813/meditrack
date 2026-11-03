import authgear, { SessionState } from '../vendor/authgear.esm.js';
import { CONFIG } from './config.js';
import { api } from './api.js';

let configured = false;
let configurePromise = null;

export const authgearClient = {
  /**
   * Initialize and configure Authgear Web SDK with backend dynamic synchronization
   */
  async init() {
    if (configured) return;
    if (configurePromise) return configurePromise;

    configurePromise = (async () => {
      // 1. Fetch live config from backend/.env so frontend is always in sync
      try {
        const resp = await api.get('/auth/config');
        if (resp?.data) {
          if (resp.data.authgear_endpoint) {
            CONFIG.AUTHGEAR_ENDPOINT = resp.data.authgear_endpoint;
          }
          if (resp.data.authgear_client_id) {
            CONFIG.AUTHGEAR_CLIENT_ID = resp.data.authgear_client_id;
          }
          if (resp.data.authgear_redirect_uri) {
            CONFIG.AUTHGEAR_REDIRECT_URI = resp.data.authgear_redirect_uri;
          }
        }
      } catch (e) {
        console.warn('Could not fetch /api/v1/auth/config, using default CONFIG:', e);
      }

      if (!CONFIG.AUTHGEAR_ENDPOINT || CONFIG.AUTHGEAR_ENDPOINT.includes('your-project')) {
        throw new Error('Authgear is not configured yet. Please update AUTHGEAR_ENDPOINT in backend/.env');
      }

      try {
        await authgear.configure({
          endpoint: CONFIG.AUTHGEAR_ENDPOINT,
          clientID: CONFIG.AUTHGEAR_CLIENT_ID,
          sessionType: 'refresh_token',
        });
        configured = true;
      } catch (err) {
        console.warn('Authgear configure error:', err);
        const msg = err.message || String(err);
        if (msg.includes('Failed to fetch')) {
          throw new Error(
            `Could not connect to Authgear at "${CONFIG.AUTHGEAR_ENDPOINT}". In Authgear Portal, ensure "http://localhost:5173/auth-callback" is added to Authorized Redirect URIs.`
          );
        }
        throw err;
      }
    })();

    return configurePromise;
  },

  /**
   * Start OIDC/PKCE Passwordless OTP Authentication flow
   */
  async login(returnUrl) {
    await this.init();

    let redirectURI = CONFIG.AUTHGEAR_REDIRECT_URI;
    if (returnUrl) {
      try {
        const url = new URL(redirectURI, window.location.origin);
        url.searchParams.set('returnUrl', returnUrl);
        redirectURI = url.toString();
      } catch (_) {}
    }

    if (typeof authgear.startAuthentication === 'function') {
      await authgear.startAuthentication({
        redirectURI,
        prompt: 'login',
      });
    } else if (typeof authgear.startAuthorization === 'function') {
      await authgear.startAuthorization({
        redirectURI,
        prompt: 'login',
      });
    } else {
      throw new Error('Authgear startAuthentication method not found.');
    }
  },

  /**
   * Handle redirect callback from Authgear
   */
  async handleCallback() {
    await this.init();

    let authResult;
    if (typeof authgear.finishAuthentication === 'function') {
      authResult = await authgear.finishAuthentication();
    } else if (typeof authgear.finishAuthorization === 'function') {
      authResult = await authgear.finishAuthorization();
    } else {
      throw new Error('Authgear finishAuthentication method not found.');
    }

    const userInfo = authResult?.userInfo || authResult;
    const accessToken = authgear.accessToken;

    let backendUser = null;
    try {
      // Sync user profile & role with MediTrack backend
      const syncResp = await api.post(
        '/auth/authgear/sync',
        { access_token: accessToken },
        { headers: { Authorization: `Bearer ${accessToken}` } }
      );
      backendUser = syncResp?.data?.user;
    } catch (e) {
      console.warn('Backend user sync fallback:', e);
    }

    return {
      userInfo,
      accessToken,
      user: backendUser || {
        id: userInfo?.sub,
        authgear_user_id: userInfo?.sub,
        email: userInfo?.email,
        phone: userInfo?.phone_number,
        name: userInfo?.name || userInfo?.formatted_name || 'Customer',
        role: 'customer',
      },
    };
  },

  /**
   * Get valid access token, auto-refreshing if expired
   */
  async getAccessToken() {
    try {
      await this.init();
      if (authgear.sessionState !== SessionState.AUTHENTICATED) {
        return null;
      }
      await authgear.refreshAccessTokenIfNeeded();
      return authgear.accessToken;
    } catch (_) {
      return null;
    }
  },

  /**
   * Check if Authgear session is currently authenticated
   */
  isAuthenticated() {
    return authgear.sessionState === SessionState.AUTHENTICATED;
  },

  /**
   * Fetch authenticated user info from Authgear
   */
  async fetchUserInfo() {
    try {
      await this.init();
      if (this.isAuthenticated()) {
        return await authgear.fetchUserInfo();
      }
    } catch (_) {}
    return null;
  },

  /**
   * Logout user from Authgear session
   */
  async logout(redirectURI = `${window.location.origin}/login`) {
    try {
      await this.init();
      await authgear.logout({ redirectURI });
    } catch (_) {
      window.location.href = redirectURI;
    }
  },

  raw: authgear,
  SessionState,
};
