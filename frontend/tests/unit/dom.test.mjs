import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

// Pure JS minimal DOM mock without any third-party dependencies
class MockNode {
  constructor() {
    this.childNodes = [];
    this.parentNode = null;
  }
  appendChild(child) {
    if (child instanceof MockNode) {
      child.parentNode = this;
      this.childNodes.push(child);
    }
    return child;
  }
  removeChild(child) {
    const idx = this.childNodes.indexOf(child);
    if (idx !== -1) {
      this.childNodes.splice(idx, 1);
      child.parentNode = null;
    }
    return child;
  }
  get firstChild() {
    return this.childNodes[0] || null;
  }
}

class MockText extends MockNode {
  constructor(text) {
    super();
    this.text = String(text);
  }
  get textContent() {
    return this.text;
  }
  set textContent(val) {
    this.text = String(val);
  }
}

class MockElement extends MockNode {
  constructor(tagName) {
    super();
    this.tagName = tagName.toUpperCase();
    this.attributes = {};
    this.eventListeners = {};
    this.className = '';
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    return this.attributes[name] || null;
  }

  addEventListener(event, handler) {
    if (!this.eventListeners[event]) {
      this.eventListeners[event] = [];
    }
    this.eventListeners[event].push(handler);
  }

  click() {
    if (this.eventListeners['click']) {
      this.eventListeners['click'].forEach((h) => h({ target: this }));
    }
  }

  get textContent() {
    return this.childNodes.map((c) => c.textContent).join('');
  }

  set textContent(val) {
    this.childNodes = [new MockText(val)];
  }

  get children() {
    return this.childNodes.filter((c) => c instanceof MockElement);
  }
}

global.Node = MockNode;
global.document = {
  createElement: (tag) => new MockElement(tag),
  createTextNode: (txt) => new MockText(txt),
};

const { el, text, clear, append, setText } = await import('../../src/js/core/dom.js');

describe('DOM Core Helpers (Safe Render)', () => {
  test('el creates element with safe text children (XSS safe)', () => {
    const malicious = '<script>alert("xss")</script>';
    const button = el(
      'button',
      { className: 'btn-primary', 'data-action': 'save' },
      [malicious]
    );

    assert.equal(button.tagName, 'BUTTON');
    assert.equal(button.className, 'btn-primary');
    assert.equal(button.getAttribute('data-action'), 'save');
    assert.equal(button.textContent, malicious);
    assert.equal(button.children.length, 0);
    assert.equal(button.childNodes.length, 1);
  });

  test('el attaches event listeners correctly', () => {
    let clicked = false;
    const btn = el('button', { onClick: () => { clicked = true; } }, ['Click']);
    btn.click();
    assert.equal(clicked, true);
  });

  test('clear removes all child elements', () => {
    const parent = global.document.createElement('div');
    parent.appendChild(global.document.createElement('span'));
    parent.appendChild(global.document.createElement('p'));
    assert.equal(parent.childNodes.length, 2);

    clear(parent);
    assert.equal(parent.childNodes.length, 0);
  });

  test('append safely adds text and element nodes', () => {
    const parent = global.document.createElement('div');
    append(parent, 'Hello ', el('strong', {}, ['World']));
    assert.equal(parent.textContent, 'Hello World');
    assert.equal(parent.childNodes.length, 2);
  });

  test('setText safely updates text content without innerHTML', () => {
    const span = global.document.createElement('span');
    setText(span, '<b>Safe</b>');
    assert.equal(span.textContent, '<b>Safe</b>');
    assert.equal(span.children.length, 0);
  });
});
