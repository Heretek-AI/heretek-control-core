const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Execute the shipped initializer with a small DOM fixture. No copied tab logic.
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/admin.js'), 'utf8');
const initializer = source.match(/\( function initToolSubtabs\(\) \{[\s\S]*?\}\s*\)\(\);/)[0];
function setup(stored) {
  function element(id, active) {
    const classes = new Set(active ? ['is-active'] : []);
    return {
      attrs: { 'data-tab': id, 'aria-selected': String(active) },
      handlers: {},
      classList: { toggle(name, on) { on ? classes.add(name) : classes.delete(name); }, contains(name) { return classes.has(name); } },
      getAttribute(name) { return this.attrs[name] || null; },
      setAttribute(name, value) { this.attrs[name] = value; },
      addEventListener(name, callback) { this.handlers[name] = callback; }
    };
  }
  const tabs = [element('breakdance', true), element('gutenberg', false)];
  const panels = [element('breakdance', true), element('gutenberg', false)];
  const context = {
    document: {
      querySelector() { return null; },
      querySelectorAll(selector) { return selector === '.elementor-mcp-subtab' ? tabs : selector === '.elementor-mcp-tabpanel' ? panels : []; }
    },
    window: { localStorage: { getItem() { return stored; }, setItem(key, value) { stored = value; } } }
  };
  vm.runInNewContext(initializer, context);
  return { tabs, panels };
}

test('removed remembered builder leaves the default panel visible', () => {
  const { tabs, panels } = setup('bricks');
  assert.equal(tabs[0].getAttribute('aria-selected'), 'true');
  assert.equal(panels[0].classList.contains('is-active'), true);
  assert.equal(panels[1].classList.contains('is-active'), false);
});

test('existing remembered tab restores and clicking switches panels', () => {
  const { tabs, panels } = setup('gutenberg');
  assert.equal(panels[0].classList.contains('is-active'), false);
  assert.equal(panels[1].classList.contains('is-active'), true);
  tabs[0].handlers.click();
  assert.equal(tabs[0].getAttribute('aria-selected'), 'true');
  assert.equal(panels[0].classList.contains('is-active'), true);
  assert.equal(panels[1].classList.contains('is-active'), false);
});
