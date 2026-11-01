/**
 * Debounce a callback function by specified delay in milliseconds.
 * @param {Function} func - Callback function
 * @param {number} [wait=250] - Delay in milliseconds
 * @returns {Function}
 */
export function debounce(func, wait = 250) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

