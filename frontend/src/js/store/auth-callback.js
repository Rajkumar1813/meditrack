import { authgearClient } from '../core/authgear.js';
import { auth } from '../core/auth.js';
import { api } from '../core/api.js';

document.addEventListener('DOMContentLoaded', async () => {
  const titleEl = document.getElementById('callback-status-title');
  const descEl = document.getElementById('callback-status-desc');
  const errorBox = document.getElementById('callback-error-box');
  const errorMsg = document.getElementById('callback-error-message');

  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');
  const returnUrl = urlParams.get('returnUrl');

  function completeLogin(token, user) {
    auth.setSession(token, user);
    if (titleEl) titleEl.textContent = 'Session Verified!';
    if (descEl) descEl.textContent = 'Redirecting to your workspace...';

    setTimeout(() => {
      if (returnUrl && returnUrl.startsWith('/')) {
        window.location.href = returnUrl;
      } else if (['admin', 'pharmacist', 'staff'].includes(user?.role)) {
        window.location.href = '/admin/index.html';
      } else {
        window.location.href = '/index.html';
      }
    }, 600);
  }

  // 1. First try browser-side PKCE code exchange via Authgear Web SDK
  try {
    const result = await authgearClient.handleCallback();
    completeLogin(result.accessToken, result.user);
    return;
  } catch (err) {
    console.warn('Browser Authgear code exchange failed, trying backend exchange fallback...', err);

    // 2. If browser exchange failed (e.g. client secret is required for confidential clients) and code exists
    if (code) {
      try {
        if (descEl) descEl.textContent = 'Exchanging authorization code via backend...';
        const exchangeResp = await api.post('/auth/authgear/exchange', {
          code,
          redirect_uri: window.location.origin + window.location.pathname,
        });

        const userData = exchangeResp?.data?.user;
        const accessToken = exchangeResp?.data?.tokens?.access_token;
        if (userData && accessToken) {
          completeLogin(accessToken, userData);
          return;
        }
      } catch (backendErr) {
        console.error('Backend Authgear code exchange error:', backendErr);
        showError(backendErr.message || err.message);
        return;
      }
    }

    showError(err.message);
  }

  function showError(message) {
    if (titleEl) titleEl.textContent = 'Authentication Failed';
    if (descEl) descEl.textContent = 'We could not complete your sign in.';
    if (errorBox && errorMsg) {
      errorBox.classList.remove('hidden');

      if (message.includes('client secret is required') || message.includes('CLIENT_SECRET_REQUIRED')) {
        errorMsg.innerHTML = `
          <strong>Authgear App Type Notice:</strong><br>
          Aapka Authgear client <em>Traditional Web App</em> ke roop mein bana hua hai jisko client secret chahiye.<br><br>
          <strong>Option 1 (Recommended):</strong> Authgear Portal mein naya app banayein aur type <strong>Single Page Application (SPA)</strong> select karein (is me secret ki zaroorat nahi hoti), fir uska Client ID <code>backend/.env</code> mein daal dein.<br><br>
          <strong>Option 2:</strong> Authgear Portal se <strong>Client Secret</strong> copy karke <code>backend/.env</code> mein <code>AUTHGEAR_CLIENT_SECRET=...</code> add karein.
        `;
      } else {
        errorMsg.textContent = message || 'An error occurred during verification. Please try again.';
      }
    }
  }
});
