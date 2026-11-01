export const modal = {
  open(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;

    el.classList.remove('hidden');
    el.classList.add('flex');
    document.body.style.overflow = 'hidden';

    // Trap escape
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        modal.close(modalId);
        document.removeEventListener('keydown', handleKeyDown);
      }
    };
    document.addEventListener('keydown', handleKeyDown);

    // Backdrop click
    const backdrop = el.querySelector('[data-modal-backdrop]') || el;
    const handleBackdrop = (e) => {
      if (e.target === backdrop) {
        modal.close(modalId);
        backdrop.removeEventListener('click', handleBackdrop);
      }
    };
    backdrop.addEventListener('click', handleBackdrop);
  },

  close(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;

    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = '';
  },
};

