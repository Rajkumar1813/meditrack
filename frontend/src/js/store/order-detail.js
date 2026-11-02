import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { createStatusBadge } from '../ui/components.js';
import { toast } from '../core/toast.js';
import { auth } from '../core/auth.js';

let currentOrder = null;

document.addEventListener('DOMContentLoaded', async () => {
  await auth.init();

  const params = new URLSearchParams(window.location.search);
  const orderId = params.get('id');

  if (!orderId) {
    window.location.href = '/orders.html';
    return;
  }

  const container = document.getElementById('order-detail-container');
  const titleEl = document.getElementById('order-heading-no');

  async function loadOrder() {
    try {
      const resp = await api.get(`/orders/${encodeURIComponent(orderId)}`);
      currentOrder = resp?.data;

      if (!currentOrder) {
        if (container) {
          clear(container);
          container.appendChild(el('div', { className: 'py-20 text-center text-red-500' }, ['Order not found.']));
        }
        return;
      }

      if (titleEl) {
        setText(titleEl, `#${currentOrder.order_no || currentOrder.id}`);
      }

      renderOrderDetails(currentOrder, container);
    } catch (err) {
      if (container) {
        clear(container);
        container.appendChild(
          el('div', { className: 'py-20 text-center text-red-500 text-sm' }, [
            err.message || 'Unable to load order details.',
          ])
        );
      }
    }
  }

  function renderOrderDetails(order, parent) {
    clear(parent);

    const isCancelled = order.status === 'cancelled';
    const steps = [
      { key: 'pending', label: 'Order Placed', desc: 'Awaiting verification' },
      { key: 'confirmed', label: 'Confirmed', desc: 'Prescription & payment verified' },
      { key: 'packed', label: 'Packed', desc: 'Pharmacist packed batches' },
      { key: 'shipped', label: 'Shipped', desc: 'Out for delivery' },
      { key: 'delivered', label: 'Delivered', desc: 'Order fulfilled' },
    ];

    const currentStepIndex = steps.findIndex((s) => s.key === order.status);

    // 1. Status Stepper Card
    const stepperCard = el('div', { className: 'p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft mb-8' });

    const stepperHeader = el('div', { className: 'flex items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-100 dark:border-slate-800' }, [
      el('div', {}, [
        el('h2', { className: 'text-base font-bold text-slate-900 dark:text-white' }, ['Fulfillment Timeline']),
        el('p', { className: 'text-xs text-slate-400 mt-0.5' }, [
          `Placed on ${new Date(order.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`,
        ]),
      ]),
      createStatusBadge(order.status),
    ]);
    append(stepperCard, stepperHeader);

    if (isCancelled) {
      const cancelBox = el('div', { className: 'p-4 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-xs leading-relaxed flex items-center gap-3' }, [
        el('span', { className: 'text-xl' }, ['✕']),
        el('div', {}, [
          el('strong', { className: 'block font-bold' }, ['Order Cancelled']),
          el('span', {}, ['All allocated inventory batches have been safely released back to active pharmacy stock. Any prepaid amount has been refunded.']),
        ]),
      ]);
      append(stepperCard, cancelBox);
    } else {
      // Stepper row
      const stepperRow = el('div', { className: 'grid grid-cols-1 sm:grid-cols-5 gap-4 relative' });
      steps.forEach((step, idx) => {
        const isDone = currentStepIndex >= idx;
        const isCurrent = currentStepIndex === idx;

        const stepCol = el('div', { className: 'flex sm:flex-col items-center sm:items-center text-left sm:text-center gap-3 sm:gap-2' }, [
          el('div', {
            className: `w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 transition ${
              isDone
                ? 'bg-primary-600 text-white shadow-soft'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-400'
            }`,
          }, [isDone ? '✓' : String(idx + 1)]),
          el('div', {}, [
            el('p', {
              className: `text-xs font-bold ${
                isCurrent
                  ? 'text-primary-600 dark:text-primary-400'
                  : isDone
                  ? 'text-slate-900 dark:text-white'
                  : 'text-slate-400'
              }`,
            }, [step.label]),
            el('p', { className: 'text-[10px] text-slate-400 hidden sm:block mt-0.5' }, [step.desc]),
          ]),
        ]);
        append(stepperRow, stepCol);
      });
      append(stepperCard, stepperRow);

      // Tracking Link if shipped
      if (order.tracking_no || order.courier) {
        const trackingBar = el('div', { className: 'mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs' }, [
          el('span', { className: 'text-slate-500' }, [
            `Courier: ${order.courier || 'Express Dispatch'} | Tracking: #${order.tracking_no || 'Assigned'}`,
          ]),
          el('span', { className: 'font-semibold text-primary-600' }, ['In Transit']),
        ]);
        append(stepperCard, trackingBar);
      }
    }
    append(parent, stepperCard);

    // 2. Order Content: Items Table + Delivery Info & Invoice Download
    const contentGrid = el('div', { className: 'grid grid-cols-1 lg:grid-cols-3 gap-8 items-start' });

    // Left 2 cols: Items table
    const itemsSection = el('div', { className: 'lg:col-span-2 space-y-6' });
    const itemsCard = el('div', { className: 'p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft' }, [
      el('h3', { className: 'text-sm font-bold text-slate-900 dark:text-white mb-4 uppercase tracking-wider' }, [
        'Order Line Items',
      ]),
      el(
        'div',
        { className: 'divide-y divide-slate-100 dark:border-slate-800' },
        (order.items || []).map((item) =>
          el('div', { className: 'py-3.5 flex items-start justify-between gap-4 text-xs' }, [
            el('div', { className: 'flex-1' }, [
              el('h4', { className: 'font-bold text-slate-800 dark:text-slate-100 text-sm' }, [
                item.name || item.medicine_name,
              ]),
              el('div', { className: 'text-slate-400 mt-1 space-x-2' }, [
                item.batch_no ? el('span', {}, [`Batch: ${item.batch_no}`]) : null,
                item.exp_date ? el('span', {}, [`Exp: ${item.exp_date}`]) : null,
              ]),
              el('div', { className: 'text-slate-500 dark:text-slate-400 mt-0.5' }, [
                `Qty: ${item.quantity} × ₹${Number(item.unit_price || item.price || 0).toFixed(2)}`,
              ]),
            ]),
            el('span', { className: 'text-sm font-bold text-slate-900 dark:text-white' }, [
              `₹${Number(item.line_total || (item.unit_price || item.price || 0) * item.quantity).toFixed(2)}`,
            ]),
          ])
        )
      ),
    ]);
    append(itemsSection, itemsCard);
    append(contentGrid, itemsSection);

    // Right col: Address snapshot & Totals card
    const sideCol = el('div', { className: 'space-y-6' });

    // Delivery Address Card
    const addr = order.shipping_address || order.address || {};
    const addrCard = el('div', { className: 'p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft text-xs' }, [
      el('h3', { className: 'font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3' }, ['Shipping Address']),
      el('p', { className: 'font-semibold text-slate-800 dark:text-slate-200 text-sm mb-1' }, [
        addr.recipient_name || addr.name || 'Customer',
      ]),
      el('p', { className: 'text-slate-500 dark:text-slate-400' }, [addr.line1 || '']),
      el('p', { className: 'text-slate-500 dark:text-slate-400' }, [
        `${addr.city || ''}, ${addr.state || ''} - ${addr.pincode || ''}`,
      ]),
      el('p', { className: 'text-slate-500 dark:text-slate-400 mt-1' }, [`Phone: ${addr.phone || 'N/A'}`]),
    ]);
    append(sideCol, addrCard);

    // Bill Details & Invoicing Card
    const billCard = el('div', { className: 'p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft space-y-4 text-xs' }, [
      el('h3', { className: 'font-bold text-slate-900 dark:text-white uppercase tracking-wider' }, ['Payment & Invoicing']),
      el('div', { className: 'space-y-2' }, [
        el('div', { className: 'flex justify-between text-slate-500' }, [
          el('span', {}, ['Subtotal']),
          el('span', { className: 'font-semibold text-slate-800 dark:text-slate-200' }, [`₹${Number(order.subtotal || 0).toFixed(2)}`]),
        ]),
        el('div', { className: 'flex justify-between text-slate-500' }, [
          el('span', {}, ['GST']),
          el('span', { className: 'font-semibold text-slate-800 dark:text-slate-200' }, [`₹${Number(order.gst_total || order.tax_total || 0).toFixed(2)}`]),
        ]),
        el('div', { className: 'flex justify-between text-slate-500' }, [
          el('span', {}, ['Delivery Fee']),
          el('span', { className: 'font-semibold text-slate-800 dark:text-slate-200' }, [
            Number(order.delivery_fee || 0) === 0 ? 'FREE' : `₹${Number(order.delivery_fee).toFixed(2)}`,
          ]),
        ]),
        el('div', { className: 'pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between text-sm font-bold text-slate-900 dark:text-white' }, [
          el('span', {}, ['Total Paid / Payable']),
          el('span', { className: 'text-primary-600 dark:text-primary-400' }, [`₹${Number(order.total || 0).toFixed(2)}`]),
        ]),
      ]),

      // Actions: Invoice Download, Reorder, Cancel
      el('div', { className: 'pt-4 space-y-2' }, [
        // Download Invoice Button
        el(
          'button',
          {
            type: 'button',
            className:
              'w-full py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold transition flex items-center justify-center gap-2',
            onClick: async () => {
              try {
                toast.info('Downloading GST Tax Invoice...');
                const token = auth.getToken();
                const downloadUrl = `/api/v1/orders/${order.id}/invoice`;
                const resp = await fetch(downloadUrl, {
                  headers: token ? { Authorization: `Bearer ${token}` } : {},
                });
                if (!resp.ok) throw new Error('Invoice not yet available or permission denied');
                const blob = await resp.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Invoice_Order_${order.order_no || order.id}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                toast.success('Invoice downloaded successfully');
              } catch (e) {
                toast.error(e.message || 'Invoice could not be downloaded.');
              }
            },
          },
          [
            el('span', {}, ['📄']),
            'Download GST Invoice',
          ]
        ),

        // 1-Tap Reorder Button
        el(
          'button',
          {
            type: 'button',
            className:
              'w-full py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-bold transition shadow-sm',
            onClick: async () => {
              try {
                await api.post(`/orders/${order.id}/reorder`, {});
                toast.success('Order items added to cart!');
                window.location.href = '/cart.html';
              } catch (e) {
                toast.error(e.message || 'Failed to reorder items');
              }
            },
          },
          ['1-Tap Reorder']
        ),

        // Cancel Order Button (Only visible if pending or confirmed)
        ['pending', 'confirmed'].includes(order.status)
          ? el(
              'button',
              {
                type: 'button',
                className: 'w-full py-2 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 text-xs font-semibold transition',
                onClick: async () => {
                  if (!confirm('Are you sure you want to cancel this order? Reserved items will be released back to pharmacy inventory.')) {
                    return;
                  }
                  try {
                    await api.post(`/orders/${order.id}/cancel`, {});
                    toast.info('Order has been cancelled');
                    await loadOrder();
                  } catch (e) {
                    toast.error(e.message || 'Unable to cancel order');
                  }
                },
              },
              ['Cancel Order']
            )
          : null,
      ]),
    ]);
    append(sideCol, billCard);
    append(contentGrid, sideCol);

    append(parent, contentGrid);
  }

  await loadOrder();
});

