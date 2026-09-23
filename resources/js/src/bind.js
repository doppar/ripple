import { findAttr, parseModifiers, payloadFrom, rootOf } from './dom.js';
import { enqueue, isBusy, send } from './http.js';
import { hydrateBoundFields } from './hydrate.js';
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

async function updateProp(root, property, value, { silent = true } = {}) {
  // Send last signed state + p/v only — do not mutate data-ripple-state before
  // the response or the HMAC signature no longer matches. The payload is built when this
  // update reaches the front of the queue, so it always starts from the previous response.
  if (!silent) startLoading(root);
  await enqueue(root, async () => {
    const data = await send(root, payloadFrom(root, { t: 'u', p: property, v: value }));
    applyResponse(root, data);
  });
}

async function uploadFiles(root, property, files, multiple) {
  startLoading(root);
  try {
    const id = root.getAttribute('data-ripple-id') || '';
    const ids = [];
    for (const f of [...files]) {
      const uploaded = await uploadFile(id, property, f);
      if (!uploaded?.id) {
        throw new Error(uploaded?.message || 'Upload failed');
      }
      ids.push(uploaded.id);
    }
    await enqueue(root, async () => {
      const data = await send(
        root,
        payloadFrom(root, { t: 'u', p: property, v: multiple ? ids : ids[0] }),
      );
      applyResponse(root, data);
    });
  } catch (err) {
    const message = err?.message || 'Upload failed';
    root.dispatchEvent(new CustomEvent('ripple:upload-error', { bubbles: true, detail: { message, property } }));
    console.error('[ripple] upload:', message);
  } finally {
    if (!isBusy(root)) stopLoading(root);
  }
}

export function bindModels(root) {
  hydrateBoundFields(root);

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
