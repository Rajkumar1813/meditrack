import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { auth } from '../core/auth.js';
import { toast } from '../core/toast.js';

document.addEventListener('DOMContentLoaded', async () => {
  const user = auth.getUser();

  // User details
  const nameEl = document.getElementById('profile-name');
  const contactEl = document.getElementById('profile-contact');
  const roleBadge = document.getElementById('profile-role-badge');
  const addressesContainer = document.getElementById('profile-addresses-container');
  const refillsContainer = document.getElementById('profile-refills-container');
  const addAddressForm = document.getElementById('profile-add-address-form');
  const toggleAddressFormBtn = document.getElementById('profile-toggle-address-btn');
  const dpdpExportBtn = document.getElementById('dpdp-export-btn');
  const dpdpDeleteBtn = document.getElementById('dpdp-delete-btn');

  // Preferences toggles
  const prefOrders = document.getElementById('pref-orders');
  const prefRefills = document.getElementById('pref-refills');
  const prefPromo = document.getElementById('pref-promo');

  if (user) {
    if (nameEl) setText(nameEl, user.name || 'MediTrack User');
    if (contactEl) setText(contactEl, user.phone || user.email || '');
    if (roleBadge) setText(roleBadge, user.role || 'customer');
  }

  // 1. Load Notification Preferences
  async function loadPreferences() {
    try {
      const resp = await api.get('/notifications/preferences');
      const prefs = resp?.data || {};

      if (prefOrders) prefOrders.checked = prefs.order_updates !== false;
      if (prefRefills) prefRefills.checked = prefs.refill_reminders !== false;
      if (prefPromo) prefPromo.checked = prefs.promotional_push === true;
    } catch (_) {}
  }

  async function savePreferences() {
    try {
      await api.put('/notifications/preferences', {
        order_updates: prefOrders?.checked ?? true,
        refill_reminders: prefRefills?.checked ?? true,
        promotional_push: prefPromo?.checked ?? false,
      });
      toast.success('Notification preferences saved');
    } catch (e) {
      toast.error(e.message || 'Failed to update preferences');
    }
  }

  [prefOrders, prefRefills, prefPromo].forEach((chk) => {
    chk?.addEventListener('change', savePreferences);
  });

  // 2. Load Addresses CRUD
  async function loadAddresses() {
    if (!addressesContainer) return;

    try {
      const resp = await api.get('/addresses');
      const addresses = resp?.data || [];

      clear(addressesContainer);

      if (addresses.length === 0) {
        addressesContainer.appendChild(
          el('div', { className: 'py-6 text-center text-xs text-slate-400' }, ['No saved addresses yet.'])
        );
        return;
      }

      addresses.forEach((addr) => {
        const item = el(
          'div',
          {
            className:
              'p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs transition',
          },
          [
            el('div', { className: 'space-y-1' }, [
              el('div', { className: 'flex items-center gap-2' }, [
                el('span', { className: 'font-bold text-slate-900 dark:text-white text-sm' }, [addr.recipient_name || addr.name]),
                addr.is_default
                  ? el('span', { className: 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' }, ['Default'])
                  : null,
              ]),
              el('p', { className: 'text-slate-500 dark:text-slate-400' }, [addr.line1]),
              el('p', { className: 'text-slate-500 dark:text-slate-400' }, [
                `${addr.city}, ${addr.state} - ${addr.pincode}`,
              ]),
              el('p', { className: 'text-slate-400' }, [`Phone: ${addr.phone}`]),
            ]),
            el('div', { className: 'flex items-center gap-2 self-end sm:self-auto' }, [
              !addr.is_default
                ? el(
                    'button',
                    {
                      type: 'button',
                      className: 'px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition',
                      onClick: async () => {
                        try {
                          await api.put(`/addresses/${addr.id}`, { ...addr, is_default: 1 });
                          toast.success('Default address updated');
                          await loadAddresses();
                        } catch (e) {
                          toast.error(e.message || 'Failed to set default address');
                        }
                      },
                    },
                    ['Set Default']
                  )
                : null,
              el(
                'button',
                {
                  type: 'button',
                  className: 'text-red-500 hover:text-red-700 px-2 py-1 font-semibold transition',
                  onClick: async () => {
                    if (!confirm('Delete this address?')) return;
                    try {
                      await api.delete(`/addresses/${addr.id}`);
                      toast.info('Address removed');
                      await loadAddresses();
                    } catch (e) {
                      toast.error(e.message || 'Failed to remove address');
                    }
                  },
                },
                ['Delete']
              ),
            ]),
          ]
        );
        addressesContainer.appendChild(item);
      });
    } catch (_) {}
  }

  // Address inline toggle & form submission
  if (toggleAddressFormBtn && addAddressForm) {
    toggleAddressFormBtn.addEventListener('click', () => {
      addAddressForm.classList.toggle('hidden');
    });

    addAddressForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('profile-addr-name')?.value.trim();
      const phone = document.getElementById('profile-addr-phone')?.value.trim();
      const line1 = document.getElementById('profile-addr-line1')?.value.trim();
      const city = document.getElementById('profile-addr-city')?.value.trim();
      const state = document.getElementById('profile-addr-state')?.value.trim();
      const pincode = document.getElementById('profile-addr-pincode')?.value.trim();

      try {
        await api.post('/addresses', {
          recipient_name: name,
          phone,
          line1,
          city,
          state,
          pincode,
          is_default: 0,
        });

        toast.success('Address saved successfully');
        addAddressForm.reset();
        addAddressForm.classList.add('hidden');
        await loadAddresses();
      } catch (err) {
        toast.error(err.message || 'Failed to save address');
      }
    });
  }

  // 3. Load Refill Reminders
  async function loadRefills() {
    if (!refillsContainer) return;

    try {
      const resp = await api.get('/refills');
      const refills = resp?.data || [];

      clear(refillsContainer);

      if (refills.length === 0) {
        refillsContainer.appendChild(
          el('div', { className: 'py-6 text-center text-xs text-slate-400' }, ['No active refill reminders.'])
        );
        return;
      }

      refills.forEach((rf) => {
        const item = el(
          'div',
          {
            className:
              'p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs transition',
          },
          [
            el('div', { className: 'space-y-1' }, [
              el('h4', { className: 'font-bold text-slate-900 dark:text-white text-sm' }, [
                rf.medicine_name,
              ]),
              el('p', { className: 'text-slate-400' }, [
                `Prescribed Course: ${rf.course_days || 30} days | Due on: ${rf.remind_at || 'Soon'}`,
              ]),
              rf.price ? el('p', { className: 'font-semibold text-emerald-600' }, [`Current Price: ₹${Number(rf.price).toFixed(2)}`]) : null,
            ]),
            el('div', { className: 'flex items-center gap-2 flex-wrap' }, [
              el(
                'button',
                {
                  type: 'button',
                  className: 'px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition',
                  onClick: async () => {
                    try {
                      await api.post(`/refills/${rf.id}/snooze`, { days: 7 });
                      toast.info('Reminder snoozed for 7 days');
                      await loadRefills();
                    } catch (e) {
                      toast.error(e.message || 'Failed to snooze');
                    }
                  },
                },
                ['Snooze 7d']
              ),
              el(
                'button',
                {
                  type: 'button',
                  className: 'px-3.5 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-700 active:scale-95 text-white font-bold shadow-soft transition',
                  onClick: async () => {
                    try {
                      await api.post(`/refills/${rf.id}/reorder`, {});
                      toast.success('Medicine loaded to cart!');
                      window.location.href = '/cart.html';
                    } catch (e) {
                      toast.error(e.message || 'Failed to reorder');
                    }
                  },
                },
                ['1-Tap Reorder']
              ),
              el(
                'button',
                {
                  type: 'button',
                  className: 'text-slate-400 hover:text-slate-600 px-2 py-1',
                  title: 'Dismiss reminder',
                  onClick: async () => {
                    try {
                      await api.post(`/refills/${rf.id}/dismiss`, {});
                      toast.info('Refill reminder dismissed');
                      await loadRefills();
                    } catch (e) {
                      toast.error(e.message || 'Failed to dismiss');
                    }
                  },
                },
                ['×']
              ),
            ]),
          ]
        );
        refillsContainer.appendChild(item);
      });
    } catch (_) {}
  }

  // 4. DPDP Act Controls
  if (dpdpExportBtn) {
    dpdpExportBtn.addEventListener('click', async () => {
      try {
        toast.info('Generating your DPDP Act personal data archive...');
        const token = auth.getToken();
        const exportUrl = `/api/v1/admin/customers/${user?.id || 1}/export`;
        const resp = await fetch(exportUrl, {
          headers: token ? { Authorization: `Bearer ${token}` } : {},
        });
        if (!resp.ok) throw new Error('Data export could not be processed');
        const data = await resp.json();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `MediTrack_Personal_Data_${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        toast.success('Personal data archive downloaded');
      } catch (e) {
        toast.error(e.message || 'Failed to export personal data');
      }
    });
  }

  if (dpdpDeleteBtn) {
    dpdpDeleteBtn.addEventListener('click', async () => {
      const confirmed = prompt(
        'Under DPDP Act 2023 Right to Erasure, type "DELETE" to permanently request erasure of your account.'
      );
      if (confirmed === 'DELETE') {
        try {
          await api.post(`/admin/customers/${user?.id || 1}/delete-request`, {
            reason: 'User self-requested account erasure via profile portal',
          });
          toast.info('Account erasure request received. All active sessions terminated.');
          await auth.logout();
        } catch (e) {
          toast.error(e.message || 'Failed to submit erasure request');
        }
      }
    });
  }

  // Run initial loads
  await Promise.all([loadPreferences(), loadAddresses(), loadRefills()]);
});

