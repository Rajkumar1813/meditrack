import { api } from '../core/api.js';
import { auth } from '../core/auth.js';
import { toast } from '../core/toast.js';
import { setText } from '../core/dom.js';

let currentMode = 'phone'; // 'phone' | 'email'
let activeIdentifier = '';
let resendInterval = null;
let rateLimitInterval = null;

export function initLoginPage() {
  const card = document.querySelector('.card-3d');
  const requestForm = document.getElementById('otp-request-form');
  const verifyForm = document.getElementById('otp-verify-form');
  const identifierInput = document.getElementById('login-identifier');
  const sendOtpBtn = document.getElementById('send-otp-btn');
  const verifyOtpBtn = document.getElementById('verify-otp-btn');
  const changeIdentifierBtn = document.getElementById('change-identifier-btn');
  const resendOtpBtn = document.getElementById('resend-otp-btn');
  const resendTimerText = document.getElementById('resend-timer-text');
  const sentTargetLabel = document.getElementById('sent-target-label');
  const errorMessageEl = document.getElementById('login-error-message');
  const rateLimitBanner = document.getElementById('rate-limit-banner');
  const rateLimitTimerEl = document.getElementById('rate-limit-seconds');

  if (!requestForm || !verifyForm) return;

  // 1. Check if user already logged in via silent refresh
  auth.init().then((user) => {
    if (user) {
      redirectUser(user);
    }
  });

  // 2. 3D Card Tilt Effect (Respects prefers-reduced-motion)
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

  // 3. Mode Toggle (Phone vs Email)
  const phoneTab = document.getElementById('tab-phone');
  const emailTab = document.getElementById('tab-email');
  const phonePrefix = document.getElementById('phone-prefix');

  function setMode(mode) {
    currentMode = mode;
    clearError();
    if (mode === 'phone') {
      phoneTab?.classList.add('bg-white', 'dark:bg-slate-800', 'shadow-sm', 'text-slate-900', 'dark:text-white');
      phoneTab?.classList.remove('text-slate-500');
      emailTab?.classList.remove('bg-white', 'dark:bg-slate-800', 'shadow-sm', 'text-slate-900', 'dark:text-white');
      emailTab?.classList.add('text-slate-500');

      if (phonePrefix) phonePrefix.classList.remove('hidden');
      if (identifierInput) {
        identifierInput.type = 'tel';
        identifierInput.placeholder = '98765 43210';
        identifierInput.value = '';
        identifierInput.focus();
      }
    } else {
      emailTab?.classList.add('bg-white', 'dark:bg-slate-800', 'shadow-sm', 'text-slate-900', 'dark:text-white');
      emailTab?.classList.remove('text-slate-500');
      phoneTab?.classList.remove('bg-white', 'dark:bg-slate-800', 'shadow-sm', 'text-slate-900', 'dark:text-white');
      phoneTab?.classList.add('text-slate-500');

      if (phonePrefix) phonePrefix.classList.add('hidden');
      if (identifierInput) {
        identifierInput.type = 'email';
        identifierInput.placeholder = 'name@example.com';
        identifierInput.value = '';
        identifierInput.focus();
      }
    }
  }

  phoneTab?.addEventListener('click', () => setMode('phone'));
  emailTab?.addEventListener('click', () => setMode('email'));

  // 4. Send OTP Request
  requestForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearError();

    const rawVal = identifierInput?.value.trim() || '';
    if (!rawVal) {
      showError(currentMode === 'phone' ? 'Please enter mobile number.' : 'Please enter email address.');
      return;
    }

    if (currentMode === 'phone') {
      const cleanDigits = rawVal.replace(/\D/g, '');
      if (cleanDigits.length < 10) {
        showError('Please enter a valid 10-digit mobile number.');
        return;
      }
      activeIdentifier = cleanDigits.length === 10 ? `+91${cleanDigits}` : `+${cleanDigits}`;
    } else {
      activeIdentifier = rawVal.toLowerCase();
    }

    setButtonLoading(sendOtpBtn, true, 'Sending...');

    try {
      const res = await api.post('/auth/otp/request', {
        identifier: activeIdentifier,
        type: currentMode,
      });

      toast.success(res?.message || 'Verification code sent.');

      // Switch to Step 2
      requestForm.classList.add('hidden');
      verifyForm.classList.remove('hidden');

      if (sentTargetLabel) {
        setText(sentTargetLabel, `Code sent to ${activeIdentifier}`);
      }

      startResendTimer(res?.data?.cooldown_seconds || 60);

      // Focus first OTP digit box
      const firstBox = document.getElementById('otp-digit-0');
      firstBox?.focus();
    } catch (err) {
      handleApiError(err);
    } finally {
      setButtonLoading(sendOtpBtn, false, 'Send OTP');
    }
  });

  // 5. Change Identifier (Go back to Step 1)
  changeIdentifierBtn?.addEventListener('click', () => {
    verifyForm.classList.add('hidden');
    requestForm.classList.remove('hidden');
    clearOtpBoxes();
    clearInterval(resendInterval);
    clearError();
    identifierInput?.focus();
  });

  // 6. Resend OTP
  resendOtpBtn?.addEventListener('click', async () => {
    clearError();
    resendOtpBtn.disabled = true;

    try {
      const res = await api.post('/auth/otp/request', {
        identifier: activeIdentifier,
        type: currentMode,
      });

      toast.success('New code sent.');
      clearOtpBoxes();
      startResendTimer(res?.data?.cooldown_seconds || 60);
      document.getElementById('otp-digit-0')?.focus();
    } catch (err) {
      handleApiError(err);
    } finally {
      resendOtpBtn.disabled = false;
    }
  });

  // 7. OTP Digits Input (6 individual boxes)
  const otpBoxes = Array.from({ length: 6 }, (_, i) => document.getElementById(`otp-digit-${i}`));
  const unifiedOtpInput = document.getElementById('login-otp'); // Fallback single input

  otpBoxes.forEach((box, idx) => {
    if (!box) return;

    box.addEventListener('input', (e) => {
      const val = e.target.value.replace(/\D/g, '');
      e.target.value = val ? val[val.length - 1] : '';

      if (val && idx < 5) {
        otpBoxes[idx + 1]?.focus();
      }

      // Check if all 6 filled
      const code = getOtpCode();
      if (code.length === 6) {
        verifyForm.requestSubmit();
      }
    });

    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && idx > 0) {
        otpBoxes[idx - 1]?.focus();
      }
    });

    box.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const digits = pasted.replace(/\D/g, '').slice(0, 6);
      digits.split('').forEach((d, i) => {
        if (otpBoxes[i]) otpBoxes[i].value = d;
      });
      const nextFocus = Math.min(digits.length, 5);
      otpBoxes[nextFocus]?.focus();

      if (digits.length === 6) {
        verifyForm.requestSubmit();
      }
    });
  });

  function getOtpCode() {
    if (unifiedOtpInput && !otpBoxes[0]) {
      return unifiedOtpInput.value.trim();
    }
    return otpBoxes.map((b) => b?.value || '').join('');
  }

  function clearOtpBoxes() {
    otpBoxes.forEach((b) => {
      if (b) b.value = '';
    });
    if (unifiedOtpInput) unifiedOtpInput.value = '';
  }

  // 8. Verify OTP Submit
  verifyForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearError();

    const code = getOtpCode();
    if (code.length !== 6) {
      showError('Please enter the complete 6-digit code.');
      return;
    }

    setButtonLoading(verifyOtpBtn, true, 'Verifying...');

    try {
      const res = await api.post('/auth/otp/verify', {
        identifier: activeIdentifier,
        code,
        client: 'web',
      });

      const { user, tokens } = res.data;
      auth.setSession(tokens.access_token, user);

      toast.success('Login successful! Redirecting...');
      setTimeout(() => redirectUser(user), 350);
    } catch (err) {
      handleApiError(err);
      clearOtpBoxes();
      otpBoxes[0]?.focus();
    } finally {
      setButtonLoading(verifyOtpBtn, false, 'Verify & Login');
    }
  });

  // 9. Rate Limit Event Listener
  window.addEventListener('meditrack:rate-limited', (e) => {
    const retryAfter = e.detail?.retryAfter || 60;
    startRateLimitCountdown(retryAfter);
  });

  function startResendTimer(seconds) {
    clearInterval(resendInterval);
    let remaining = seconds;

    if (resendTimerText) {
      resendTimerText.classList.remove('hidden');
      setText(resendTimerText, `Resend code in ${remaining}s`);
    }
    if (resendOtpBtn) {
      resendOtpBtn.classList.add('hidden');
    }

    resendInterval = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(resendInterval);
        if (resendTimerText) resendTimerText.classList.add('hidden');
        if (resendOtpBtn) resendOtpBtn.classList.remove('hidden');
      } else {
        if (resendTimerText) {
          setText(resendTimerText, `Resend code in ${remaining}s`);
        }
      }
    }, 1000);
  }

  function startRateLimitCountdown(seconds) {
    if (!rateLimitBanner) return;
    clearInterval(rateLimitInterval);
    let remaining = seconds;

    rateLimitBanner.classList.remove('hidden');
    if (rateLimitTimerEl) setText(rateLimitTimerEl, `${remaining}s`);

    if (sendOtpBtn) sendOtpBtn.disabled = true;
    if (verifyOtpBtn) verifyOtpBtn.disabled = true;

    rateLimitInterval = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(rateLimitInterval);
        rateLimitBanner.classList.add('hidden');
        if (sendOtpBtn) sendOtpBtn.disabled = false;
        if (verifyOtpBtn) verifyOtpBtn.disabled = false;
      } else {
        if (rateLimitTimerEl) setText(rateLimitTimerEl, `${remaining}s`);
      }
    }, 1000);
  }

  function handleApiError(err) {
    if (err.status === 429 || err.code === 'RATE_LIMITED') {
      const wait = err.retryAfter || 60;
      startRateLimitCountdown(wait);
      showError(err.message || `Rate limit reached. Please wait ${wait}s.`);
      return;
    }

    if (err.fields) {
      const fieldKeys = Object.keys(err.fields);
      const firstMsg = err.fields[fieldKeys[0]][0];
      showError(firstMsg);
      return;
    }

    showError(err.message || 'An error occurred. Please try again.');
  }

  function showError(msg) {
    if (!errorMessageEl) return;
    setText(errorMessageEl, msg);
    errorMessageEl.classList.remove('hidden');
  }

  function clearError() {
    if (!errorMessageEl) return;
    setText(errorMessageEl, '');
    errorMessageEl.classList.add('hidden');
  }

  function setButtonLoading(btn, isLoading, text) {
    if (!btn) return;
    btn.disabled = isLoading;
    if (isLoading) {
      btn.classList.add('opacity-75', 'cursor-not-allowed');
      setText(btn, text);
    } else {
      btn.classList.remove('opacity-75', 'cursor-not-allowed');
      setText(btn, text);
    }
  }

  function redirectUser(user) {
    const role = user?.role || 'customer';
    if (['admin', 'pharmacist', 'staff'].includes(role)) {
      window.location.href = '/admin/index.html';
    } else {
      window.location.href = '/index.html';
    }
  }
}

// Auto-run if on login page
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLoginPage);
  } else {
    initLoginPage();
  }
}

