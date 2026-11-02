import { api } from '../core/api.js';
import { el, text, clear, setText, append } from '../core/dom.js';

let cartState = null;
let addressesState = [];
let selectedAddressId = null;
let activeOrderId = null;

const alertBox = document.getElementById('checkout-alert');
const cartItemsContainer = document.getElementById('checkout-cart-items');
const addressesContainer = document.getElementById('addresses-list');
const placeOrderBtn = document.getElementById('place-order-btn');
const placeOrderText = document.getElementById('place-order-btn-text');
const placeOrderSpinner = document.getElementById('place-order-spinner');

async function initCheckout() {
  setLoading(true);
  hideAlert();

  try {
    const [cartRes, addrRes] = await Promise.all([
      api.get('/cart'),
      api.get('/addresses')
    ]);

    cartState = cartRes.data;
    addressesState = addrRes.data || [];

    if (!cartState || cartState.item_count === 0) {
      showEmptyCart();
      return;
    }

    renderCart(cartState);
    renderAddresses(addressesState);
  } catch (err) {
    showAlert(err.message || 'Failed to load checkout details.', false);
  } finally {
    setLoading(false);
  }
}

function renderCart(cart) {
  clear(cartItemsContainer);

  if (!cart.items || cart.items.length === 0) {
    showEmptyCart();
    return;
  }

  cart.items.forEach(item => {
    const row = el('div', { class: 'py-3 flex justify-between items-center text-sm' }, [
      el('div', { class: 'flex-1 pr-3' }, [
        el('h4', { class: 'font-semibold text-slate-800 dark:text-slate-100' }, [text(item.name)]),
        el('div', { class: 'text-xs text-slate-500 dark:text-slate-400 mt-0.5' }, [
          text(`Qty: ${item.quantity} × ₹${item.unit_price.toFixed(2)}`),
          item.requires_rx ? el('span', { class: 'ml-2 text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded' }, [text('Rx Required')]) : null
        ])
      ]),
      el('span', { class: 'font-bold text-slate-900 dark:text-white' }, [text(`₹${item.line_total.toFixed(2)}`)])
    ]);

    cartItemsContainer.appendChild(row);
  });

  // Totals breakdown
  setText(document.getElementById('summary-subtotal'), `₹${cart.subtotal.toFixed(2)}`);
  setText(document.getElementById('summary-gst'), `₹${cart.gst_total.toFixed(2)}`);
  setText(document.getElementById('summary-delivery'), cart.delivery_fee > 0 ? `₹${cart.delivery_fee.toFixed(2)}` : 'FREE');
  setText(document.getElementById('summary-total'), `₹${cart.total.toFixed(2)}`);

  // Rx notice box
  const rxNotice = document.getElementById('rx-notice-box');
  if (cart.has_rx_items) {
    rxNotice.classList.remove('hidden');
  } else {
    rxNotice.classList.add('hidden');
  }
}

function renderAddresses(addresses) {
  clear(addressesContainer);

  if (!addresses || addresses.length === 0) {
    addressesContainer.appendChild(
      el('p', { class: 'text-sm text-slate-500 dark:text-slate-400' }, [
        text('No delivery addresses found. Please add an address below.')
      ])
    );
    toggleAddressForm(true);
    return;
  }

  // Pre-select default address
  const defaultAddr = addresses.find(a => Number(a.is_default) === 1) || addresses[0];
  selectedAddressId = defaultAddr ? defaultAddr.id : null;

  addresses.forEach(addr => {
    const isSelected = addr.id === selectedAddressId;
    const label = el('label', {
      class: `flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition ${
        isSelected
          ? 'border-primary-500 bg-primary-50/20 dark:bg-primary-900/10'
          : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300'
      }`
    }, [
      el('input', {
        type: 'radio',
        name: 'delivery_address',
        value: String(addr.id),
        class: 'mt-1 text-primary-600 focus:ring-primary-500',
        ...(isSelected ? { checked: true } : {})
      }),
      el('div', { class: 'flex-1 text-xs' }, [
        el('div', { class: 'flex items-center gap-2 mb-0.5' }, [
          el('strong', { class: 'text-sm text-slate-900 dark:text-white' }, [text(addr.recipient_name)]),
          el('span', { class: 'text-slate-500 dark:text-slate-400' }, [text(`(${addr.phone})`)]),
          Number(addr.is_default) === 1
            ? el('span', { class: 'px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-semibold' }, [text('Default')])
            : null
        ]),
        el('p', { class: 'text-slate-600 dark:text-slate-400 leading-relaxed' }, [
          text(`${addr.line1}${addr.line2 ? ', ' + addr.line2 : ''}, ${addr.city}, ${addr.state} - ${addr.pincode}`)
        ])
      ])
    ]);

    label.addEventListener('click', () => {
      selectedAddressId = addr.id;
      renderAddresses(addresses);
    });

    addressesContainer.appendChild(label);
  });
}

function toggleAddressForm(show) {
  const form = document.getElementById('new-address-form');
  if (show) {
    form.classList.remove('hidden');
  } else {
    form.classList.add('hidden');
  }
}

document.getElementById('toggle-new-address-btn')?.addEventListener('click', () => {
  const form = document.getElementById('new-address-form');
  toggleAddressForm(form.classList.contains('hidden'));
});

document.getElementById('cancel-new-address-btn')?.addEventListener('click', () => {
  toggleAddressForm(false);
});

document.getElementById('new-address-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = document.getElementById('new-addr-name').value.trim();
  const phone = document.getElementById('new-addr-phone').value.trim();
  const line1 = document.getElementById('new-addr-line1').value.trim();
  const city = document.getElementById('new-addr-city').value.trim();
  const state = document.getElementById('new-addr-state').value.trim();
  const pincode = document.getElementById('new-addr-pincode').value.trim();

  try {
    const res = await api.post('/addresses', {
      recipient_name: name,
      phone,
      line1,
      city,
      state,
      pincode,
      is_default: addressesState.length === 0 ? 1 : 0
    });

    const newAddr = res.data;
    addressesState.unshift(newAddr);
    selectedAddressId = newAddr.id;
    renderAddresses(addressesState);
    toggleAddressForm(false);
    e.target.reset();
  } catch (err) {
    showAlert(err.message || 'Failed to save address.', false);
  }
});

async function handlePlaceOrder() {
  const checkedAddr = document.querySelector('input[name="delivery_address"]:checked');
  if (checkedAddr) {
    selectedAddressId = parseInt(checkedAddr.value, 10);
  } else if (!selectedAddressId && addressesState.length > 0) {
    const defaultAddr = addressesState.find(a => Number(a.is_default) === 1) || addressesState[0];
    selectedAddressId = defaultAddr ? defaultAddr.id : null;
  }

  if (!selectedAddressId) {
    showAlert('Please select or add a delivery address.', false);
    return;
  }

  const paymentMethodInput = document.querySelector('input[name="payment_method"]:checked');
  const paymentMethod = paymentMethodInput ? paymentMethodInput.value : 'upi';

  setLoading(true);
  hideAlert();

  try {
    let order = null;

    // 1. Create order if not already created (or reuse in retry)
    if (!activeOrderId) {
      const idempotencyKey = 'order_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
      const orderRes = await api.post('/orders', {
        address_id: selectedAddressId,
        payment_method: paymentMethod
      }, {
        headers: { 'Idempotency-Key': idempotencyKey }
      });

      order = orderRes.data;
      activeOrderId = order.id;
    }

    // 2. COD flow -> redirect to order detail page
    if (paymentMethod === 'cod') {
      window.location.href = `/order.html?id=${activeOrderId}`;
      return;
    }

    // 3. Online Razorpay payment flow
    const payRes = await api.post('/payments/create', {
      order_id: activeOrderId,
      gateway: 'razorpay'
    });

    const payData = payRes.data;

    // Razorpay standard modal
    const rzpOptions = {
      key: payData.key_id,
      amount: payData.amount_paise,
      currency: payData.currency,
      name: window.BRAND?.app?.name || 'MediTrack',
      description: `Order #${payData.order_no}`,
      order_id: payData.gateway_order_id,
      handler: async function (response) {
        setLoading(true);
        try {
          await api.post('/payments/verify', {
            order_id: activeOrderId,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature
          });

          window.location.href = `/order.html?id=${activeOrderId}`;
        } catch (verifyErr) {
          showAlert(verifyErr.message || 'Payment verification failed.', true);
        } finally {
          setLoading(false);
        }
      },
      modal: {
        ondismiss: function () {
          setLoading(false);
          showAlert('Payment was cancelled or dismissed. You can retry safely below.', true);
        }
      },
      theme: {
        color: '#0284c7'
      }
    };

    if (window.Razorpay) {
      const rzp = new window.Razorpay(rzpOptions);
      rzp.on('payment.failed', function (response) {
        showAlert(response.error.description || 'Payment transaction failed. Please retry.', true);
        setLoading(false);
      });
      rzp.open();
    } else {
      // Direct mock verification in development when Razorpay script is blocked or offline
      await api.post('/payments/verify', {
        order_id: activeOrderId,
        razorpay_order_id: payData.gateway_order_id,
        razorpay_payment_id: 'pay_mock_' + Date.now(),
        razorpay_signature: 'sig_mock_dev'
      });
      window.location.href = `/orders.html?confirmed=${activeOrderId}`;
    }
  } catch (err) {
    showAlert(err.message || 'Failed to place order.', activeOrderId !== null);
  } finally {
    setLoading(false);
  }
}

function showAlert(message, isRetryable) {
  clear(alertBox);
  alertBox.className = 'mb-6 p-4 rounded-2xl border border-red-200 bg-red-50 dark:bg-red-950/40 dark:border-red-900/60 text-xs text-red-800 dark:text-red-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3';

  const textSpan = el('span', { class: 'flex-1' }, [text(message)]);
  alertBox.appendChild(textSpan);

  if (isRetryable && activeOrderId) {
    const retryBtn = el('button', {
      type: 'button',
      class: 'px-4 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs transition shadow-sm'
    }, [text('Retry Payment')]);

    retryBtn.addEventListener('click', () => {
      handlePlaceOrder();
    });

    alertBox.appendChild(retryBtn);
  }

  alertBox.classList.remove('hidden');
}

function hideAlert() {
  alertBox.classList.add('hidden');
  clear(alertBox);
}

function showEmptyCart() {
  clear(cartItemsContainer);
  cartItemsContainer.appendChild(
    el('div', { class: 'py-8 text-center text-slate-500 dark:text-slate-400' }, [
      el('p', { class: 'text-sm font-semibold' }, [text('Your cart is empty.')]),
      el('a', { href: '/index.html', class: 'mt-3 inline-block text-xs font-bold text-primary-600 hover:underline' }, [text('← Continue Shopping')])
    ])
  );
  if (placeOrderBtn) {
    placeOrderBtn.disabled = true;
    placeOrderBtn.classList.add('opacity-50', 'cursor-not-allowed');
  }
}

function setLoading(isLoading) {
  if (!placeOrderBtn) return;
  placeOrderBtn.disabled = isLoading;
  if (isLoading) {
    placeOrderText?.classList.add('opacity-0');
    placeOrderSpinner?.classList.remove('hidden');
  } else {
    placeOrderText?.classList.remove('opacity-0');
    placeOrderSpinner?.classList.add('hidden');
  }
}

placeOrderBtn?.addEventListener('click', handlePlaceOrder);

initCheckout();

