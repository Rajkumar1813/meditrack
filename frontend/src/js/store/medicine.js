import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { createMedicineCard, createPillSvg } from '../ui/components.js';
import { init3DTilt } from '../ui/tilt-3d.js';
import { toast } from '../core/toast.js';
import { auth } from '../core/auth.js';

let currentMedicine = null;
let selectedQuantity = 1;

document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const slug = params.get('slug');

  if (!slug) {
    window.location.href = '/catalog.html';
    return;
  }

  const container = document.getElementById('medicine-detail-container');
  const alternativesContainer = document.getElementById('alternatives-container');
  const breadcrumbName = document.getElementById('breadcrumb-medicine-name');

  try {
    const resp = await api.get(`/medicines/${encodeURIComponent(slug)}`);
    currentMedicine = resp?.data;

    if (!currentMedicine) {
      if (container) {
        clear(container);
        container.appendChild(
          el('div', { className: 'py-20 text-center text-red-500' }, ['Medicine not found.'])
        );
      }
      return;
    }

    if (breadcrumbName) {
      setText(breadcrumbName, currentMedicine.name);
    }

    renderMedicineDetails(currentMedicine, container);
    init3DTilt(container);

    // Fetch same-salt alternatives
    const primarySalt = currentMedicine.salts?.[0]?.name || currentMedicine.generic_name;
    if (primarySalt && alternativesContainer) {
      loadAlternatives(primarySalt, currentMedicine.id, alternativesContainer);
    }
  } catch (err) {
    if (container) {
      clear(container);
      container.appendChild(
        el('div', { className: 'py-20 text-center text-red-500' }, [
          err.message || 'Unable to load medicine details.',
        ])
      );
    }
  }
});

function renderMedicineDetails(med, container) {
  clear(container);

  const priceVal = med.price !== undefined ? med.price : med.selling_price;
  const mrpVal = med.mrp;
  const maxStock = med.stock || 100;
  const inStock = Boolean(med.in_stock !== false && (med.stock === undefined || med.stock > 0));

  const wrapper = el('div', { className: 'grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12' });

  // Left Column: Visual 3D Product Presentation Card
  const visualCard = el(
    'div',
    {
      className:
        'card-3d rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 flex flex-col items-center justify-center min-h-[360px] shadow-soft relative overflow-hidden',
      dataset: { tilt: 'true' },
    },
    [
      el(
        'div',
        {
          className:
            'w-32 h-32 rounded-3xl bg-gradient-to-tr from-primary-50 to-primary-100 dark:from-primary-950/60 dark:to-primary-900/40 text-primary-600 dark:text-primary-400 flex items-center justify-center mb-6 shadow-soft',
        },
        [
          el('div', { className: 'w-16 h-16 transform scale-150' }, [createPillSvg()]),
        ]
      ),
      el('span', { className: 'text-xs font-semibold text-slate-400 uppercase tracking-wider' }, [
        med.category_name || 'Pharmaceutical',
      ]),
      el('div', { className: 'mt-2 flex items-center gap-2' }, [
        med.requires_rx
          ? el(
              'span',
              {
                className:
                  'px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
              },
              ['Prescription Required (Rx)']
            )
          : el(
              'span',
              {
                className:
                  'px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
              },
              ['Over-The-Counter (OTC)']
            ),
      ]),
    ]
  );
  append(wrapper, visualCard);

  // Right Column: Details, Salts, Pricing & Actions
  const detailsCol = el('div', { className: 'flex flex-col justify-between space-y-6' });

  // Header info
  const headerSection = el('div', {}, [
    el('h1', { className: 'text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-2' }, [
      med.name,
    ]),
    med.generic_name
      ? el('p', { className: 'text-sm font-medium text-slate-500 dark:text-slate-400 mb-2' }, [
          'Generic: ',
          el('span', { className: 'text-slate-800 dark:text-slate-200 font-semibold' }, [med.generic_name]),
        ])
      : null,
    med.manufacturer
      ? el('p', { className: 'text-xs text-slate-400' }, [`Manufacturer: ${med.manufacturer}`])
      : null,
  ]);
  append(detailsCol, headerSection);

  // Composition Salts Box
  if (med.salts && med.salts.length > 0) {
    const saltsBox = el(
      'div',
      { className: 'p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800' },
      [
        el('h2', { className: 'text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2.5' }, [
          'Active Composition / Salts',
        ]),
        el(
          'ul',
          { className: 'space-y-1.5' },
          med.salts.map((s) =>
            el('li', { className: 'text-xs font-medium text-slate-700 dark:text-slate-200 flex items-center justify-between' }, [
              el('span', { className: 'flex items-center gap-1.5' }, [
                el('span', { className: 'w-1.5 h-1.5 rounded-full bg-primary-500' }),
                s.name,
              ]),
              s.strength ? el('span', { className: 'font-semibold text-slate-900 dark:text-white' }, [s.strength]) : null,
            ])
          )
        ),
      ]
    );
    append(detailsCol, saltsBox);
  }

  // Price & Stock Display
  const priceBox = el('div', { className: 'p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft' }, [
    el('div', { className: 'flex items-baseline gap-3 mb-2' }, [
      el('span', { className: 'text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight' }, [
        priceVal ? `₹${Number(priceVal).toFixed(2)}` : 'N/A',
      ]),
      mrpVal && Number(mrpVal) > Number(priceVal)
        ? el('span', { className: 'text-sm text-slate-400 line-through' }, [`MRP ₹${Number(mrpVal).toFixed(2)}`])
        : null,
      mrpVal && Number(mrpVal) > Number(priceVal)
        ? el(
            'span',
            { className: 'text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950 px-2 py-0.5 rounded-full' },
            [`Save ${Math.round(((mrpVal - priceVal) / mrpVal) * 100)}%`]
          )
        : null,
    ]),
    el('p', { className: 'text-xs text-slate-400' }, ['Inclusive of all taxes. Fulfilled via FEFO (earliest expiry batch).']),
  ]);
  append(detailsCol, priceBox);

  // Quantity Picker & Add to Cart
  const actionRow = el('div', { className: 'flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-2' });

  // Quantity control
  const qtyPicker = el('div', { className: 'flex items-center border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-800 p-1 shrink-0' });
  const minusBtn = el(
    'button',
    {
      type: 'button',
      className: 'w-9 h-9 rounded-lg flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 transition',
      onClick: () => {
        if (selectedQuantity > 1) {
          selectedQuantity--;
          setText(qtyDisplay, selectedQuantity);
        }
      },
    },
    ['−']
  );
  const qtyDisplay = el('span', { className: 'w-10 text-center text-sm font-bold text-slate-800 dark:text-white' }, [
    String(selectedQuantity),
  ]);
  const plusBtn = el(
    'button',
    {
      type: 'button',
      className: 'w-9 h-9 rounded-lg flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 transition',
      onClick: () => {
        if (selectedQuantity < maxStock) {
          selectedQuantity++;
          setText(qtyDisplay, selectedQuantity);
        }
      },
    },
    ['+']
  );
  append(qtyPicker, minusBtn, qtyDisplay, plusBtn);
  append(actionRow, qtyPicker);

  // Add to Cart Button
  const addToCartBtn = el(
    'button',
    {
      type: 'button',
      disabled: !inStock,
      className: `flex-1 py-3.5 px-6 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition active:scale-95 ${
        inStock
          ? 'bg-primary-600 hover:bg-primary-700 text-white shadow-soft'
          : 'bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed'
      }`,
      onClick: async () => {
        if (!inStock) return;

        if (!auth.isAuthenticated()) {
          toast.info('Please log in to add medicines to your cart');
          setTimeout(() => {
            window.location.href = `/login.html?returnUrl=${encodeURIComponent(window.location.pathname + window.location.search)}`;
          }, 800);
          return;
        }

        try {
          addToCartBtn.disabled = true;
          setText(addToCartBtn, 'Adding to Cart...');

          await api.post('/cart/items', {
            medicine_id: med.id,
            quantity: selectedQuantity,
          });

          setText(addToCartBtn, 'Added to Cart ✓');
          addToCartBtn.classList.remove('bg-primary-600');
          addToCartBtn.classList.add('bg-emerald-700');

          toast.success(`${selectedQuantity} × ${med.name} added to cart`);
          window.dispatchEvent(new CustomEvent('meditrack:cart-updated'));

          setTimeout(() => {
            setText(addToCartBtn, 'Add to Cart');
            addToCartBtn.classList.remove('bg-emerald-700');
            addToCartBtn.classList.add('bg-primary-600');
            addToCartBtn.disabled = false;
          }, 1500);
        } catch (err) {
          addToCartBtn.disabled = false;
          setText(addToCartBtn, 'Add to Cart');
          toast.error(err.message || 'Failed to add item to cart');
        }
      },
    },
    [inStock ? 'Add to Cart' : 'Out of Stock']
  );
  append(actionRow, addToCartBtn);
  append(detailsCol, actionRow);

  // Schedule H Warning Note
  if (med.requires_rx) {
    const rxNotice = el(
      'div',
      {
        className:
          'p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 leading-relaxed',
      },
      [
        '⚠️ Indian Drugs & Cosmetics Act Notice: This medicine requires a valid doctor prescription. You can upload your prescription image during checkout.',
      ]
    );
    append(detailsCol, rxNotice);
  }

  append(wrapper, detailsCol);
  append(container, wrapper);
}

async function loadAlternatives(saltQuery, excludeId, container) {
  try {
    const resp = await api.get(`/medicines?q=${encodeURIComponent(saltQuery)}&limit=4`);
    const list = (resp?.data || []).filter((m) => m.id !== excludeId);

    if (list.length === 0) {
      container.parentElement?.classList.add('hidden');
      return;
    }

    clear(container);
    list.forEach((item) => {
      container.appendChild(createMedicineCard(item));
    });
    init3DTilt(container);
  } catch (_) {
    container.parentElement?.classList.add('hidden');
  }
}

