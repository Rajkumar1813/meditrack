import { api } from '../core/api.js';
import { auth } from '../core/auth.js';
import { authgearClient } from '../core/authgear.js';
import { toast } from '../core/toast.js';

export function initLoginPage() {
  const card = document.querySelector('.card-3d');
  const authgearBtn = document.getElementById('authgear-login-btn');
  const authgearBtnText = document.getElementById('authgear-btn-text');
  const errorBox = document.getElementById('login-error-message');
  const toggleDevLogin = document.getElementById('toggle-dev-login');
  const devLoginPanel = document.getElementById('dev-login-panel');

  // 1. Get returnUrl from query string
  const urlParams = new URLSearchParams(window.location.search);
  const returnUrl = urlParams.get('returnUrl');

  function redirectUser(user) {
    if (returnUrl && returnUrl.startsWith('/')) {
      window.location.href = returnUrl;
    } else if (['admin', 'pharmacist', 'staff'].includes(user?.role)) {
      window.location.href = '/admin/index.html';
    } else {
      window.location.href = '/index.html';
    }
  }

  // 2. Check if user already logged in via active session
  auth.init().then((user) => {
    if (user) {
      redirectUser(user);
    }
  });

  // 3. 3D Card Tilt Effect (Respects prefers-reduced-motion)
  if (card) {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!prefersReducedMotion) {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        const rotateX = ((y - centerY) / centerY) * -6; // Max 6 deg
        const rotateY = ((x - centerX) / centerX) * 6;

        card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) scale3d(1.01, 1.01, 1.01)`;
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
      });
    }
  }

  // 4. Authgear Primary Login Button Action
  if (authgearBtn) {
    authgearBtn.addEventListener('click', async () => {
      try {
        if (authgearBtnText) {
          authgearBtnText.textContent = 'Redirecting to Authgear...';
        }
        authgearBtn.disabled = true;
        authgearBtn.classList.add('opacity-80', 'cursor-not-allowed');

        await authgearClient.login(returnUrl);
      } catch (err) {
        console.error('Authgear login error:', err);
        if (authgearBtnText) {
          authgearBtnText.textContent = 'Sign In / Register with OTP';
        }
        authgearBtn.disabled = false;
        authgearBtn.classList.remove('opacity-80', 'cursor-not-allowed');

        if (errorBox) {
          errorBox.classList.remove('hidden');
          errorBox.textContent = err.message || 'Unable to connect to Authgear. Please verify internet connection.';
        }
        if (devLoginPanel) {
          devLoginPanel.classList.remove('hidden');
        }
      }
    });
  }

  // 5. Developer / Local Offline Fallback
  if (toggleDevLogin && devLoginPanel) {
    toggleDevLogin.addEventListener('click', () => {
      devLoginPanel.classList.toggle('hidden');
    });

    const devButtons = devLoginPanel.querySelectorAll('[data-dev-role]');
    devButtons.forEach((btn) => {
      btn.addEventListener('click', async () => {
        const role = btn.dataset.devRole;
        try {
          btn.disabled = true;
          // Instant developer 1-click login without OTP cooldown
          const loginResp = await api.post('/auth/dev-login', { role });

          const userData = loginResp?.data?.user;
          const token = loginResp?.data?.tokens?.access_token || loginResp?.data?.access_token;

          if (userData && token) {
            auth.setSession(token, userData);
            toast.success(`Signed in as ${userData.name || role}`);
            redirectUser(userData);
          }
        } catch (err) {
          toast.error(err.message || 'Local sign in failed');
        } finally {
          btn.disabled = false;
        }
      });
    });
  }
}
