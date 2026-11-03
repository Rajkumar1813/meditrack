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
      }
    })();

    return configurePromise;
  },

  /**
   * Start OIDC/PKCE Passwordless OTP Authorization flow
   */
  async login(returnUrl) {
    await this.init();

    let redirectURI = CONFIG.AUTHGEAR_REDIRECT_URI;
    if (returnUrl) {
      const url = new URL(redirectURI, window.location.origin);
      url.searchParams.set('returnUrl', returnUrl);
      redirectURI = url.toString();
    }

    await authgear.startAuthorization({
      redirectURI,
      prompt: 'login',
    });
  },

  /**
   * Handle redirect callback from Authgear
   */
  async handleCallback() {
    await this.init();
    const userInfo = await authgear.finishAuthorization();
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
    await this.init();
    if (authgear.sessionState !== SessionState.AUTHENTICATED) {
      return null;
    }

    try {
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
    await this.init();
    if (this.isAuthenticated()) {
      try {
        return await authgear.fetchUserInfo();
      } catch (_) {
        return null;
      }
    }
    return null;
  },

  /**
   * Logout user from Authgear session
   */
  async logout(redirectURI = `${window.location.origin}/login.html`) {
    await this.init();
    try {
      await authgear.logout({ redirectURI });
    } catch (_) {
      window.location.href = redirectURI;
    }
  },

  raw: authgear,
  SessionState,
};
