/**
 * Safe DOM rendering helpers.
 * Prevents XSS by never inserting dynamic data into innerHTML.
 */

/**
 * Create an element with attributes, event listeners, and safe children.
 * @param {string} tag - HTML tag name
 * @param {Object} [attrs={}] - Attributes, classes, datasets, or event listeners (on*)
 * @param {Array<Node|string>} [children=[]] - Safe child nodes or strings (rendered via textContent)
 * @returns {HTMLElement}
 */
export function el(tag, attrs = {}, children = []) {
  const element = document.createElement(tag);

  for (const [key, value] of Object.entries(attrs)) {
    if (value === null || value === undefined) {
      continue;
    }

    if (key === 'className' || key === 'class') {
      element.className = value;
    } else if (key.startsWith('on') && typeof value === 'function') {
      const eventName = key.slice(2).toLowerCase();
      element.addEventListener(eventName, value);
    } else if (key.startsWith('data-')) {
      element.setAttribute(key, value);
    } else if (key === 'dataset' && typeof value === 'object') {
      for (const [dataKey, dataVal] of Object.entries(value)) {
        element.dataset[dataKey] = dataVal;
      }
    } else if (key === 'checked' || key === 'disabled' || key === 'selected') {
      element[key] = Boolean(value);
    } else {
      element.setAttribute(key, value);
    }
  }

  const childList = Array.isArray(children) ? children : [children];
  for (const child of childList) {
    if (child === null || child === undefined || child === false) {
      continue;
    }
    if (child instanceof Node) {
      element.appendChild(child);
    } else {
      element.appendChild(document.createTextNode(String(child)));
    }
  }

  return element;
}

/**
 * Create a safe text node.
 * @param {string} content
 * @returns {Text}
 */
export function text(content) {
  return document.createTextNode(content !== null && content !== undefined ? String(content) : '');
}

/**
 * Remove all child nodes from a container safely.
 * @param {HTMLElement} parent
 */
export function clear(parent) {
  if (!parent) return;
  while (parent.firstChild) {
    parent.removeChild(parent.firstChild);
  }
}

/**
 * Safely append multiple children or text strings to a parent.
 * @param {HTMLElement} parent
 * @param {...(Node|string)} children
 */
export function append(parent, ...children) {
  if (!parent) return;
  for (const child of children) {
    if (child instanceof Node) {
      parent.appendChild(child);
    } else if (child !== null && child !== undefined) {
      parent.appendChild(document.createTextNode(String(child)));
    }
  }
}

/**
 * Set safe textContent on an element.
 * @param {HTMLElement} element
 * @param {string} value
 */
export function setText(element, value) {
  if (element) {
    element.textContent = value !== null && value !== undefined ? String(value) : '';
  }
}

