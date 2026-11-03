import authgear, { SessionState } from '../vendor/authgear.esm.js';
import { CONFIG } from './config.js';
import { api } from './api.js';

let configured = false;
let configurePromise = null;

export const authgearClient = {
  /**
   * Initialize and configure Authgear Web SDK
   */
  async init() {
    if (configured) return;
    if (configurePromise) return configurePromise;

    configurePromise = (async () => {
      try {
        await authgear.configure({
          endpoint: CONFIG.AUTHGEAR_ENDPOINT,
          clientID: CONFIG.AUTHGEAR_CLIENT_ID,
          sessionType: 'refresh_token',
        });
        configured = true;
      } catch (err) {
        console.warn('Authgear configuration warning:', err);
        throw err;
      }
    })();

    return configurePromise;
  },

  /**
   * Start OIDC/PKCE Passwordless OTP Authentication flow
   */
  async login(returnUrl) {
    try {
      await this.init();
    } catch (err) {
      throw new Error(
        `Unable to connect to Authgear endpoint (${CONFIG.AUTHGEAR_ENDPOINT}). Please verify your AUTHGEAR_ENDPOINT and AUTHGEAR_CLIENT_ID in .env, or use Developer Login below.`
      );
    }

    let redirectURI = CONFIG.AUTHGEAR_REDIRECT_URI;
    if (returnUrl) {
      try {
        const url = new URL(redirectURI, window.location.origin);
        url.searchParams.set('returnUrl', returnUrl);
        redirectURI = url.toString();
      } catch (_) {}
    }

    // Call startAuthentication (Authgear Web SDK v6 standard method)
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
      throw new Error('Authgear authentication method not found on SDK container.');
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
      throw new Error('Authgear finishAuthentication method not found on SDK container.');
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
  async logout(redirectURI = `${window.location.origin}/login.html`) {
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
