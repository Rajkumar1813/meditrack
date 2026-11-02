import { api } from '../core/api.js';
import { el, append, clear } from '../core/dom.js';
import { createMedicineCard, createSkeletonCard } from '../ui/components.js';
import { init3DTilt } from '../ui/tilt-3d.js';
import { auth } from '../core/auth.js';

document.addEventListener('DOMContentLoaded', async () => {
  const categoriesContainer = document.getElementById('category-chips-container');
  const popularContainer = document.getElementById('popular-medicines-container');
  const refillBannerContainer = document.getElementById('refill-teaser-container');

  // 1. Render Skeletons in Popular Medicines
  if (popularContainer) {
    clear(popularContainer);
    for (let i = 0; i < 4; i++) {
      popularContainer.appendChild(createSkeletonCard());
    }
  }

  // 2. Fetch and render categories
  try {
    const catResp = await api.get('/categories');
    const categories = catResp?.data || [];
    if (categoriesContainer && categories.length > 0) {
      clear(categoriesContainer);
      categories.forEach((cat) => {
        const chip = el(
          'a',
          {
            href: `/catalog.html?category=${encodeURIComponent(cat.slug || cat.name)}`,
            className:
              'card-3d h-20 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 flex flex-col items-center justify-center text-center group hover:border-primary-500 transition',
            dataset: { tilt: 'true' },
          },
          [
            el('span', { className: 'text-lg mb-1 group-hover:scale-110 transition-transform' }, [cat.icon || '💊']),
            el('span', { className: 'text-xs font-semibold text-slate-800 dark:text-slate-200 group-hover:text-primary-600 truncate w-full' }, [cat.name]),
          ]
        );
        categoriesContainer.appendChild(chip);
      });
      init3DTilt(categoriesContainer);
    }
  } catch (_) {}

  // 3. Fetch and render popular medicines
  try {
    const medResp = await api.get('/medicines?in_stock=1&limit=8');
    const medicines = medResp?.data || [];
    if (popularContainer) {
      clear(popularContainer);
      if (medicines.length === 0) {
        popularContainer.appendChild(
          el('div', { className: 'col-span-full text-center py-12 text-slate-400 text-sm' }, ['No medicines in catalog yet.'])
        );
      } else {
        medicines.forEach((med) => {
          popularContainer.appendChild(createMedicineCard(med));
        });
        init3DTilt(popularContainer);
      }
    }
  } catch (_) {
    if (popularContainer) {
      clear(popularContainer);
      popularContainer.appendChild(
        el('div', { className: 'col-span-full text-center py-12 text-red-500 text-sm' }, ['Unable to load medicines at this time.'])
      );
    }
  }

  // 4. Check if authenticated user has pending Refill Reminders
  if (refillBannerContainer && auth.isAuthenticated()) {
    try {
      const refillResp = await api.get('/refills');
      const refills = refillResp?.data || [];
      if (refills.length > 0) {
        const dueRefill = refills[0];
        clear(refillBannerContainer);

        const banner = el(
          'div',
          {
            className:
              'mb-8 p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/40 dark:to-orange-950/30 border border-amber-200 dark:border-amber-800/60 shadow-soft flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4',
          },
          [
            el('div', { className: 'flex items-center gap-3.5' }, [
              el(
                'div',
                {
                  className:
                    'w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 font-bold',
                },
                ['🔔']
              ),
              el('div', {}, [
                el('h3', { className: 'text-sm font-bold text-slate-900 dark:text-white' }, [
                  `Refill Due: ${dueRefill.medicine_name}`,
                ]),
                el('p', { className: 'text-xs text-slate-600 dark:text-slate-400' }, [
                  'Your prescribed course is nearing completion. 1-Tap reorder to avoid missing a dose.',
                ]),
              ]),
            ]),
            el('div', { className: 'flex items-center gap-2 self-end sm:self-auto' }, [
              el(
                'a',
                {
                  href: '/profile.html#refills',
                  className:
                    'px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 active:scale-95 text-white text-xs font-semibold shadow-sm transition',
                },
                ['View Refills']
              ),
            ]),
          ]
        );

        refillBannerContainer.appendChild(banner);
        refillBannerContainer.classList.remove('hidden');
      }
    } catch (_) {}
  }
});

