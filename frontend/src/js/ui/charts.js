import { el, clear } from '../core/dom.js';

const SVG_NS = 'http://www.w3.org/2000/svg';

function createSvgEl(tag, attrs = {}) {
  const elem = document.createElementNS(SVG_NS, tag);
  for (const [key, val] of Object.entries(attrs)) {
    if (val !== undefined && val !== null) {
      const attrName = key === 'className' ? 'class' : key;
      elem.setAttribute(attrName, String(val));
    }
  }
  return elem;
}

/**
 * Render inline SVG Donut Chart for Categories / Distribution
 * @param {HTMLElement} container
 * @param {Object} options
 * @param {Array<{label: string, value: number, color?: string}>} options.data
 * @param {string} [options.centerTitle]
 * @param {string} [options.centerSubtitle]
 */
export function renderDonutChart(container, options = {}) {
  if (!container) return;
  clear(container);

  const data = (options.data || []).filter((d) => (d.value || 0) > 0);
  const total = data.reduce((acc, d) => acc + d.value, 0);

  if (total === 0) {
    const emptyMsg = el(
      'div',
      { className: 'flex flex-col items-center justify-center p-8 text-slate-400 text-xs' },
      ['No category data available']
    );
    container.appendChild(emptyMsg);
    return;
  }

  const defaultColors = [
    '#10b981', // emerald
    '#3b82f6', // blue
    '#f59e0b', // amber
    '#8b5cf6', // purple
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#f97316', // orange
    '#64748b', // slate
  ];

  const wrapper = el('div', { className: 'flex flex-col sm:flex-row items-center gap-6' });

  // SVG Donut
  const size = 180;
  const strokeWidth = 24;
  const radius = 60;
  const cx = size / 2;
  const cy = size / 2;
  const circumference = 2 * Math.PI * radius;

  const svg = createSvgEl('svg', {
    viewBox: `0 0 ${size} ${size}`,
    className: 'w-36 h-36 shrink-0 -rotate-90 transform',
  });

  // Background ring
  const bgCircle = createSvgEl('circle', {
    cx,
    cy,
    r: radius,
    fill: 'none',
    stroke: 'currentColor',
    'stroke-width': strokeWidth,
    className: 'text-slate-100 dark:text-slate-800',
  });
  svg.appendChild(bgCircle);

  let accumulatedPercent = 0;
  data.forEach((item, idx) => {
    const percent = item.value / total;
    const strokeDasharray = `${percent * circumference} ${circumference}`;
    const strokeDashoffset = -accumulatedPercent * circumference;
    const color = item.color || defaultColors[idx % defaultColors.length];

    const segment = createSvgEl('circle', {
      cx,
      cy,
      r: radius,
      fill: 'none',
      stroke: color,
      'stroke-width': strokeWidth,
      'stroke-dasharray': strokeDasharray,
      'stroke-dashoffset': String(strokeDashoffset),
      class: 'transition-all duration-500 ease-out hover:opacity-90',
    });

    const titleEl = createSvgEl('title');
    titleEl.textContent = `${item.label}: ${item.value} (${Math.round(percent * 100)}%)`;
    segment.appendChild(titleEl);

    svg.appendChild(segment);
    accumulatedPercent += percent;
  });

  // Donut center display
  const chartWrapper = el('div', { className: 'relative flex items-center justify-center' });
  chartWrapper.appendChild(svg);

  const centerOverlay = el('div', {
    className: 'absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center',
  });
  const countEl = el('span', { className: 'text-xl font-bold text-slate-900 dark:text-white leading-tight' }, [
    String(total),
  ]);
  const subEl = el(
    'span',
    { className: 'text-[10px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider' },
    [options.centerSubtitle || 'Units']
  );
  centerOverlay.appendChild(countEl);
  centerOverlay.appendChild(subEl);
  chartWrapper.appendChild(centerOverlay);

  wrapper.appendChild(chartWrapper);

  // Legend
  const legend = el('div', { className: 'flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2.5 w-full text-xs' });
  data.forEach((item, idx) => {
    const color = item.color || defaultColors[idx % defaultColors.length];
    const pct = Math.round((item.value / total) * 100);

    const legendItem = el('div', { className: 'flex items-center justify-between gap-2 p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/40 transition' });
    const leftSide = el('div', { className: 'flex items-center gap-2 min-w-0' });
    const dot = el('span', {
      className: 'w-2.5 h-2.5 rounded-full shrink-0',
      style: `background-color: ${color};`,
    });
    const labelSpan = el('span', { className: 'truncate text-slate-700 dark:text-slate-300 font-medium' }, [item.label]);
    leftSide.appendChild(dot);
    leftSide.appendChild(labelSpan);

    const rightSide = el('div', { className: 'text-right shrink-0' });
    const valueSpan = el('span', { className: 'font-semibold text-slate-900 dark:text-slate-100 ml-1.5' }, [String(item.value)]);
    const pctSpan = el('span', { className: 'text-slate-400 text-[11px] ml-1' }, [`(${pct}%)`]);
    rightSide.appendChild(valueSpan);
    rightSide.appendChild(pctSpan);

    legendItem.appendChild(leftSide);
    legendItem.appendChild(rightSide);
    legend.appendChild(legendItem);
  });

  wrapper.appendChild(legend);
  container.appendChild(wrapper);
}

/**
 * Render inline SVG Bar Chart for Expiry Overview
 * @param {HTMLElement} container
 * @param {Array<{label: string, count: number, color: string, badgeClass: string}>} items
 */
export function renderExpiryBars(container, items = []) {
  if (!container) return;
  clear(container);

  const total = items.reduce((acc, it) => acc + (it.count || 0), 0);
  const maxVal = Math.max(...items.map((i) => i.count || 0), 1);

  const list = el('div', { className: 'space-y-3.5' });

  items.forEach((item) => {
    const row = el('div', { className: 'space-y-1.5' });
    const header = el('div', { className: 'flex items-center justify-between text-xs' });

    const labelGroup = el('div', { className: 'flex items-center gap-2' });
    const dot = el('span', { className: 'w-2 h-2 rounded-full shrink-0', style: `background-color: ${item.color};` });
    const title = el('span', { className: 'font-medium text-slate-700 dark:text-slate-300' }, [item.label]);
    labelGroup.appendChild(dot);
    labelGroup.appendChild(title);

    const countGroup = el('div', { className: 'flex items-center gap-2' });
    const pct = total > 0 ? Math.round((item.count / total) * 100) : 0;
    const pctBadge = el('span', { className: 'text-[10px] text-slate-400' }, [`${pct}%`]);
    const countBadge = el(
      'span',
      { className: `px-2 py-0.5 rounded-full text-[11px] font-bold ${item.badgeClass}` },
      [`${item.count} batches`]
    );
    countGroup.appendChild(pctBadge);
    countGroup.appendChild(countBadge);

    header.appendChild(labelGroup);
    header.appendChild(countGroup);
    row.appendChild(header);

    // Progress bar
    const barTrack = el('div', { className: 'h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden' });
    const pctOfMax = Math.min(100, Math.round((item.count / maxVal) * 100));
    const barFill = el('div', {
      className: 'h-full rounded-full transition-all duration-500 ease-out',
      style: `width: ${pctOfMax}%; background-color: ${item.color};`,
    });
    barTrack.appendChild(barFill);
    row.appendChild(barTrack);

    list.appendChild(row);
  });

  container.appendChild(list);
}

