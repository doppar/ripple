import { findAttr, rootOf } from './dom.js';

/**
 * Fill ripple:bind controls from data-ripple-state.
 * Skips the focused control so in-flight typing is not wiped by a sibling update.
 */
export function hydrateBoundFields(root) {
  let state = {};
  try {
    state = JSON.parse(root.getAttribute('data-ripple-state') || '{}');
  } catch (_) {}

  const active = typeof document !== 'undefined' ? document.activeElement : null;

  root.querySelectorAll('input, textarea, select').forEach((el) => {
    if (rootOf(el) !== root) return;
    if (el === active) return;
    const attr = findAttr(el, 'ripple:bind');
    if (!attr || el.type === 'file') return;
    const property = attr.value;
    if (!Object.prototype.hasOwnProperty.call(state, property)) return;

    const v = state[property];
    if (el.type === 'checkbox') {
      el.checked = !!v;
    } else if (el.type === 'radio') {
      el.checked = String(el.value) === String(v);
    } else {
      el.value = v == null ? '' : String(v);
    }
  });
}
