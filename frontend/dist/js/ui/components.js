import { el, append, setText } from '../core/dom.js';
import { api } from '../core/api.js';
import { toast } from '../core/toast.js';
import { auth } from '../core/auth.js';

/**
 * Creates an accessible, high-performance medicine card with 3D tilt.
 * Uses 100% safe DOM element creation — zero dynamic innerHTML.
 */
export function createMedicineCard(med, { onAddToCart = null } = {}) {
  const card = el('article', {
    className:
      'card-3d bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative transition-all group',
    dataset: { tilt: 'true' },
  });

  // Top header: Rx badge & Stock status
  const topRow = el('div', { className: 'flex items-center justify-between gap-2 mb-3' });

  if (med.requires_rx) {
    const rxBadge = el(
      'span',
      {
        className:
          'px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 flex items-center gap-1',
        title: 'Prescription Required for this medication',
      },
      ['Rx Required']
    );
    append(topRow, rxBadge);
  } else {
    append(topRow, el('span', { className: 'text-[11px] text-slate-400 font-medium' }, ['OTC']));
  }

  const inStock = Boolean(med.in_stock !== false && (med.stock === undefined || med.stock > 0));
  const stockBadge = el(
    'span',
    {
      className: `text-[11px] font-semibold px-2 py-0.5 rounded-full ${
        inStock
          ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
          : 'bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400'
      }`,
    },
    [inStock ? 'In Stock' : 'Out of Stock']
  );
  append(topRow, stockBadge);
  append(card, topRow);

  // Middle content: Icon/Illustration, Title, Subtitle, Category
  const middle = el('div', { className: 'flex-1 mb-4' });

  // Pill icon avatar
  const iconWrap = el(
    'div',
    {
      className:
        'w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-200',
    },
    [
      createPillSvg(),
    ]
  );
  append(middle, iconWrap);

  const titleLink = el(
    'a',
    {
      href: `/medicine.html?slug=${encodeURIComponent(med.slug || '')}`,
      className:
        'block text-base font-bold text-slate-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition leading-snug mb-1 line-clamp-1',
    },
    [med.name || 'Unnamed Medicine']
  );
  append(middle, titleLink);

  if (med.generic_name) {
    const generic = el(
      'p',
      { className: 'text-xs text-slate-500 dark:text-slate-400 mb-1.5 line-clamp-1 font-medium' },
      [med.generic_name]
    );
    append(middle, generic);
  }

  if (med.category_name || med.manufacturer) {
    const metaTag = el(
      'span',
      {
        className:
          'inline-block text-[11px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md',
      },
      [med.category_name || med.manufacturer]
    );
    append(middle, metaTag);
  }

  append(card, middle);

  // Bottom row: Pricing & Add to Cart button
  const bottomRow = el('div', {
    className: 'pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2',
  });

  const priceCol = el('div', { className: 'flex flex-col' });
  const priceVal = med.price !== undefined ? med.price : med.selling_price;
  const mrpVal = med.mrp;

  const currentPrice = el(
    'span',
    { className: 'text-lg font-bold text-slate-900 dark:text-white tracking-tight' },
    [priceVal ? `₹${Number(priceVal).toFixed(2)}` : 'N/A']
  );
  append(priceCol, currentPrice);

  if (mrpVal && Number(mrpVal) > Number(priceVal)) {
    const mrpStrike = el(
      'span',
      { className: 'text-[11px] text-slate-400 line-through' },
      [`MRP ₹${Number(mrpVal).toFixed(2)}`]
    );
    append(priceCol, mrpStrike);
  }
  append(bottomRow, priceCol);

  // Add to Cart Button
  const addBtn = el(
    'button',
    {
      type: 'button',
      disabled: !inStock,
      className: `px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition ${
        inStock
          ? 'bg-primary-600 hover:bg-primary-700 active:scale-95 text-white shadow-sm'
          : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-600 cursor-not-allowed'
      }`,
      ariaLabel: `Add ${med.name} to cart`,
      onClick: async (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (!inStock) return;

        if (!auth.isAuthenticated()) {
          toast.info('Please log in to add medicines to your cart');
          setTimeout(() => {
            window.location.href = `/login.html?returnUrl=${encodeURIComponent(window.location.pathname + window.location.search)}`;
          }, 800);
          return;
        }

        try {
          addBtn.disabled = true;
          setText(addBtn, 'Adding...');

          await api.post('/cart/items', {
            medicine_id: med.id,
            quantity: 1,
          });

          setText(addBtn, 'Added ✓');
          addBtn.classList.remove('bg-primary-600');
          addBtn.classList.add('bg-emerald-700');

          toast.success(`${med.name} added to cart`);
          window.dispatchEvent(new CustomEvent('meditrack:cart-updated'));

          if (onAddToCart) onAddToCart(med);

          setTimeout(() => {
            setText(addBtn, 'Add to Cart');
            addBtn.classList.remove('bg-emerald-700');
            addBtn.classList.add('bg-primary-600');
            addBtn.disabled = false;
          }, 1500);
        } catch (err) {
          addBtn.disabled = false;
          setText(addBtn, 'Add to Cart');
          toast.error(err.message || 'Failed to add item to cart');
        }
      },
    },
    [inStock ? 'Add to Cart' : 'Out of Stock']
  );

  append(bottomRow, addBtn);
  append(card, bottomRow);

  return card;
}

/**
 * Create a reusable SVG Pill illustration for cards.
 */
export function createPillSvg() {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'w-5 h-5');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');

  const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('d', 'm10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z');
  const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
  line.setAttribute('x1', '8.5');
  line.setAttribute('y1', '8.5');
  line.setAttribute('x2', '15.5');
  line.setAttribute('y2', '15.5');

  svg.appendChild(path);
  svg.appendChild(line);
  return svg;
}

/**
 * Creates color-coded status badges for orders & prescriptions.
 */
export function createStatusBadge(status) {
  const s = String(status || '').toLowerCase();
  const styles = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    packed: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
    shipped: 'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300 border-purple-200 dark:border-purple-800',
    delivered: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-950/80 dark:text-red-300 border-red-200 dark:border-red-800',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950/80 dark:text-red-300 border-red-200 dark:border-red-800',
  };

  const badgeStyle = styles[s] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';

  return el(
    'span',
    {
      className: `px-2.5 py-1 rounded-full text-xs font-semibold capitalize border inline-flex items-center gap-1 ${badgeStyle}`,
    },
    [s.replace('_', ' ')]
  );
}

/**
 * Creates a skeleton card placeholder for smooth loading with zero layout shift.
 */
export function createSkeletonCard() {
  const card = el('div', {
    className:
      'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col justify-between h-64 skeleton-shimmer',
  });

  const top = el('div', { className: 'flex justify-between items-center mb-4' }, [
    el('div', { className: 'w-16 h-4 bg-slate-200 dark:bg-slate-800 rounded' }),
    el('div', { className: 'w-14 h-4 bg-slate-200 dark:bg-slate-800 rounded' }),
  ]);
  const middle = el('div', { className: 'space-y-2 mb-4' }, [
    el('div', { className: 'w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-xl mb-2' }),
    el('div', { className: 'w-3/4 h-5 bg-slate-200 dark:bg-slate-800 rounded' }),
    el('div', { className: 'w-1/2 h-3.5 bg-slate-200 dark:bg-slate-800 rounded' }),
  ]);
  const bottom = el('div', { className: 'flex justify-between items-center pt-3 border-t border-slate-100 dark:border-slate-800' }, [
    el('div', { className: 'w-16 h-6 bg-slate-200 dark:bg-slate-800 rounded' }),
    el('div', { className: 'w-20 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl' }),
  ]);

  append(card, top, middle, bottom);
  return card;
}

