import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { toast } from '../core/toast.js';
import { auth } from '../core/auth.js';
import { createPillSvg } from '../ui/components.js';

document.addEventListener('DOMContentLoaded', async () => {
  await auth.init();

  const cartItemsContainer = document.getElementById('cart-items-container');
  const cartSummaryContainer = document.getElementById('cart-summary-container');
  const emptyCartState = document.getElementById('empty-cart-state');
  const cartContentSection = document.getElementById('cart-content-section');
  const clearCartBtn = document.getElementById('clear-cart-btn');

  if (!auth.isAuthenticated()) {
    if (cartContentSection) cartContentSection.classList.add('hidden');
    if (emptyCartState) {
      clear(emptyCartState);
      const guestPrompt = el('div', { className: 'py-16 text-center max-w-sm mx-auto' }, [
        el('div', { className: 'w-16 h-16 rounded-3xl bg-primary-50 dark:bg-primary-950/50 text-primary-600 flex items-center justify-center mx-auto mb-4 text-2xl shadow-soft' }, ['🛒']),
        el('h2', { className: 'text-xl font-bold text-slate-900 dark:text-white mb-2' }, ['Your Cart is Waiting']),
        el('p', { className: 'text-xs text-slate-500 dark:text-slate-400 mb-6 leading-relaxed' }, ['Log in with your phone or email to view your saved cart items and access your prescriptions.']),
        el('a', {
          href: '/login.html?returnUrl=/cart.html',
          className: 'px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs shadow-soft transition inline-block',
        }, ['Log In to Continue']),
      ]);
      append(emptyCartState, guestPrompt);
      emptyCartState.classList.remove('hidden');
    }
    return;
  }

  async function fetchAndRenderCart() {
    try {
      const resp = await api.get('/cart');
      const cart = resp?.data;
      const items = cart?.items || [];

      window.dispatchEvent(new CustomEvent('meditrack:cart-updated'));

      if (items.length === 0) {
        if (cartContentSection) cartContentSection.classList.add('hidden');
        if (emptyCartState) emptyCartState.classList.remove('hidden');
        return;
      }

      if (emptyCartState) emptyCartState.classList.add('hidden');
      if (cartContentSection) cartContentSection.classList.remove('hidden');

      // Render Item Rows
      clear(cartItemsContainer);
      items.forEach((item) => {
        const row = el(
          'div',
          {
            className:
              'p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition',
          },
          [
            // Medicine info
            el('div', { className: 'flex items-start gap-3.5 flex-1' }, [
              el('div', { className: 'w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-600 flex items-center justify-center shrink-0 mt-0.5' }, [
                createPillSvg(),
              ]),
              el('div', { className: 'min-w-0' }, [
                el('a', {
                  href: `/medicine.html?slug=${encodeURIComponent(item.slug || '')}`,
                  className: 'text-sm sm:text-base font-bold text-slate-900 dark:text-white hover:text-primary-600 transition truncate block',
                }, [item.name || item.medicine_name]),
                item.generic_name
                  ? el('p', { className: 'text-xs text-slate-400 truncate mt-0.5' }, [item.generic_name])
                  : null,
                el('div', { className: 'mt-2 flex items-center gap-2 flex-wrap' }, [
                  item.requires_rx
                    ? el('span', { className: 'px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }, ['Rx Required'])
                    : null,
                  el('span', { className: 'text-xs font-semibold text-slate-700 dark:text-slate-300' }, [`₹${Number(item.price || item.unit_price).toFixed(2)} each`]),
                ]),
              ]),
            ]),

            // Controls & Line Total
            el('div', { className: 'flex items-center justify-between sm:justify-end gap-6 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-800' }, [
              // Quantity stepper
              el('div', { className: 'flex items-center border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-800 p-0.5' }, [
                el('button', {
                  type: 'button',
                  className: 'w-8 h-8 rounded-lg flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 transition',
                  onClick: async () => {
                    if (item.quantity > 1) {
                      await updateQuantity(item.id, item.quantity - 1);
                    } else {
                      await removeItem(item.id);
                    }
                  },
                }, ['−']),
                el('span', { className: 'w-8 text-center text-xs font-bold text-slate-800 dark:text-white' }, [
                  String(item.quantity),
                ]),
                el('button', {
                  type: 'button',
                  className: 'w-8 h-8 rounded-lg flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 transition',
                  onClick: async () => {
                    await updateQuantity(item.id, item.quantity + 1);
                  },
                }, ['+']),
              ]),

              // Total & Delete
              el('div', { className: 'text-right min-w-[80px]' }, [
                el('div', { className: 'text-base font-bold text-slate-900 dark:text-white' }, [
                  `₹${Number((item.price || item.unit_price) * item.quantity).toFixed(2)}`,
                ]),
                el('button', {
                  type: 'button',
                  className: 'text-xs text-red-500 hover:text-red-700 font-semibold mt-1 transition',
                  onClick: async () => {
                    await removeItem(item.id);
                  },
                }, ['Remove']),
              ]),
            ]),
          ]
        );
        cartItemsContainer.appendChild(row);
      });

      // Render Summary Card
      renderSummary(cart);
    } catch (err) {
      toast.error(err.message || 'Error loading cart');
    }
  }

  async function updateQuantity(itemId, newQty) {
    try {
      await api.put(`/cart/items/${itemId}`, { quantity: newQty });
      await fetchAndRenderCart();
    } catch (err) {
      toast.error(err.message || 'Failed to update quantity');
    }
  }

  async function removeItem(itemId) {
    try {
      await api.delete(`/cart/items/${itemId}`);
      toast.info('Item removed from cart');
      await fetchAndRenderCart();
    } catch (err) {
      toast.error(err.message || 'Failed to remove item');
    }
  }

  function renderSummary(cart) {
    if (!cartSummaryContainer) return;
    clear(cartSummaryContainer);

    const subtotal = Number(cart.subtotal || 0);
    const tax = Number(cart.tax_total || 0);
    const deliveryFee = Number(cart.delivery_fee || 0);
    const total = Number(cart.total || subtotal + tax + deliveryFee);
    const hasRx = Boolean(cart.has_rx || (cart.items || []).some((i) => i.requires_rx));

    const card = el(
      'div',
      {
        className:
          'card-3d rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft space-y-5 sticky top-20',
        dataset: { tilt: 'true' },
      },
      [
        el('h2', { className: 'text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3' }, [
          'Order Summary',
        ]),

        // Breakdown lines
        el('div', { className: 'space-y-3 text-xs' }, [
          el('div', { className: 'flex justify-between text-slate-600 dark:text-slate-400' }, [
            el('span', {}, ['Items Subtotal']),
            el('span', { className: 'font-semibold text-slate-900 dark:text-white' }, [`₹${subtotal.toFixed(2)}`]),
          ]),
          el('div', { className: 'flex justify-between text-slate-600 dark:text-slate-400' }, [
            el('span', {}, ['Estimated GST']),
            el('span', { className: 'font-semibold text-slate-900 dark:text-white' }, [`₹${tax.toFixed(2)}`]),
          ]),
          el('div', { className: 'flex justify-between text-slate-600 dark:text-slate-400' }, [
            el('span', {}, ['Delivery Fee']),
            deliveryFee === 0
              ? el('span', { className: 'font-bold text-emerald-600 dark:text-emerald-400' }, ['FREE'])
              : el('span', { className: 'font-semibold text-slate-900 dark:text-white' }, [`₹${deliveryFee.toFixed(2)}`]),
          ]),
          el('div', { className: 'pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between text-base font-extrabold text-slate-900 dark:text-white' }, [
            el('span', {}, ['Total Payable']),
            el('span', { className: 'text-primary-600 dark:text-primary-400' }, [`₹${total.toFixed(2)}`]),
          ]),
        ]),

        // Rx Warning if cart contains prescription medicines
        hasRx
          ? el(
              'div',
              {
                className:
                  'p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 leading-relaxed',
              },
              [
                '⚠️ Your cart contains prescription medicines. You will be prompted to upload or select a verified prescription during checkout.',
              ]
            )
          : null,

        // Checkout Button
        el(
          'a',
          {
            href: '/checkout.html',
            className:
              'w-full py-3.5 px-6 rounded-2xl bg-primary-600 hover:bg-primary-700 active:scale-95 text-white font-bold text-sm shadow-soft flex items-center justify-center gap-2 transition',
          },
          ['Proceed to Checkout →']
        ),
      ]
    );

    append(cartSummaryContainer, card);
  }

  if (clearCartBtn) {
    clearCartBtn.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to clear your cart?')) return;
      try {
        await api.delete('/cart');
        toast.info('Cart cleared');
        await fetchAndRenderCart();
      } catch (err) {
        toast.error(err.message || 'Failed to clear cart');
      }
    });
  }

  await fetchAndRenderCart();
});

