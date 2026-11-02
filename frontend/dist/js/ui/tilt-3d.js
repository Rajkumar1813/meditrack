/**
 * 3D Perspective Card Tilt
 * Respects prefers-reduced-motion and brand settings.
 */

const DEFAULT_MAX_TILT = 8; // degrees

export function init3DTilt(root = document) {
  if (typeof window === 'undefined') return;

  const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (motionQuery.matches) {
    return;
  }

  const elements = root.querySelectorAll('.card-3d, [data-tilt]');
  elements.forEach((card) => {
    if (card._tiltInitialized) return;
    card._tiltInitialized = true;

    const maxTilt = Number(card.dataset.tiltMax) || DEFAULT_MAX_TILT;

    card.style.transformStyle = 'preserve-3d';
    card.style.willChange = 'transform';
    card.style.transition = 'transform 0.15s ease-out, box-shadow 0.2s ease';

    const onMouseMove = (e) => {
      if (motionQuery.matches) return;
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      const xPercent = Math.max(0, Math.min(1, x / rect.width));
      const yPercent = Math.max(0, Math.min(1, y / rect.height));

      const rotateX = -((yPercent - 0.5) * maxTilt * 2).toFixed(2);
      const rotateY = ((xPercent - 0.5) * maxTilt * 2).toFixed(2);

      card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
    };

    const onMouseLeave = () => {
      card.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) translateY(0px)';
    };

    card.addEventListener('mousemove', onMouseMove, { passive: true });
    card.addEventListener('mouseleave', onMouseLeave, { passive: true });
  });
}

