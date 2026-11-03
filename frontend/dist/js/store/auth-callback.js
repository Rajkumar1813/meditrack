import { authgearClient } from '../core/authgear.js';
import { auth } from '../core/auth.js';
import { toast } from '../core/toast.js';

document.addEventListener('DOMContentLoaded', async () => {
  const titleEl = document.getElementById('callback-status-title');
  const descEl = document.getElementById('callback-status-desc');
  const errorBox = document.getElementById('callback-error-box');
  const errorMsg = document.getElementById('callback-error-message');

  try {
    const result = await authgearClient.handleCallback();
    const user = result.user;
    const token = result.accessToken;

    auth.setSession(token, user);

    if (titleEl) titleEl.textContent = 'Session Verified!';
    if (descEl) descEl.textContent = 'Redirecting to your workspace...';

    // Retrieve returnUrl if present
    const urlParams = new URLSearchParams(window.location.search);
    const returnUrl = urlParams.get('returnUrl');

    setTimeout(() => {
      if (returnUrl && returnUrl.startsWith('/')) {
        window.location.href = returnUrl;
      } else if (['admin', 'pharmacist', 'staff'].includes(user?.role)) {
        window.location.href = '/admin/index.html';
      } else {
        window.location.href = '/index.html';
      }
    }, 600);
  } catch (err) {
    console.error('Authgear callback error:', err);
    if (titleEl) titleEl.textContent = 'Authentication Failed';
    if (descEl) descEl.textContent = 'We could not complete your sign in.';
    if (errorBox && errorMsg) {
      errorBox.classList.remove('hidden');
      errorMsg.textContent = err.message || 'An error occurred during verification. Please try again.';
    }
  }
});
