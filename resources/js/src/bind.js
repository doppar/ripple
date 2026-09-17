import { findAttr, parseModifiers, payloadFrom, rootOf } from './dom.js';
import { send } from './http.js';
import { startLoading, stopLoading } from './loading.js';
import { applyResponse } from './patch.js';
import { uploadFile } from './upload.js';

function debounce(fn, wait) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
}

function inputValue(el) {
  if (el.type === 'checkbox') return el.checked;
  if (el.type === 'radio') return el.checked ? el.value : undefined;
  return el.value;
}

async function updateProp(root, property, value) {
  startLoading(root);
  try {
    const data = await send(root, payloadFrom(root, { t: 'u', p: property, v: value }));
    applyResponse(root, data);
  } finally {
    stopLoading(root);
  }
}

async function uploadFiles(root, property, files, multiple) {
  startLoading(root);
  try {
    const id = root.getAttribute('data-ripple-id') || '';
    const ids = [];
    for (const f of [...files]) {
      const uploaded = await uploadFile(id, property, f);
      ids.push(uploaded.id);
    }
    const data = await send(
      root,
      payloadFrom(root, { t: 'u', p: property, v: multiple ? ids : ids[0] }),
    );
    applyResponse(root, data);
  } finally {
    stopLoading(root);
  }
}

export function bindModels(root) {
  root.addEventListener('input', (e) => {
    const el = e.target;
    const attr = findAttr(el, 'ripple:bind');
    if (!attr || el.type === 'file') return;
    const mods = parseModifiers(attr.name);
    const property = attr.value;
    const value = inputValue(el);
    if (mods.live) {
      if (!el._rippleHandler) {
        let handler = (v) => updateProp(rootOf(el) || root, property, v);
        if (mods.debounce) handler = debounce(handler, mods.debounce);
        el._rippleHandler = handler;
      }
      el._rippleHandler(value);
    } else {
      const mount = rootOf(el) || root;
      try {
        const state = JSON.parse(mount.getAttribute('data-ripple-state') || '{}');
        state[property] = value;
        mount.setAttribute('data-ripple-state', JSON.stringify(state));
      } catch (_) {}
    }
  });

  root.addEventListener('change', (e) => {
    const el = e.target;
    const attr = findAttr(el, 'ripple:bind');
    if (!attr) return;
    const property = attr.value;
    const mods = parseModifiers(attr.name);
    const mount = rootOf(el) || root;

    if (el.type === 'file') {
      if (el.files?.length) uploadFiles(mount, property, el.files, el.multiple);
      return;
    }

    const value = inputValue(el);
    if (el.type === 'radio' && !el.checked) return;

    if (mods.lazy || el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio' || !mods.live) {
      if (!mods.live) updateProp(mount, property, value);
    }
  });
}
