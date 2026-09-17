export function updateUri() {
  return document.querySelector('meta[name="ripple-update"]')?.content || '/ripple/update';
}

export function lazyUri() {
  return document.querySelector('meta[name="ripple-lazy"]')?.content || '/ripple/lazy';
}

export function uploadUri() {
  return document.querySelector('meta[name="ripple-upload"]')?.content || '/ripple/upload';
}

export function rootOf(el) {
  return el.closest('[data-ripple]');
}

export function contentRoot(mount) {
  return mount.firstElementChild || mount;
}

export function payloadFrom(root, extras = {}) {
  let state = {};
  try {
    state = JSON.parse(root.getAttribute('data-ripple-state') || '{}');
  } catch (_) {}

  return {
    i: root.getAttribute('data-ripple-id'),
    n: root.getAttribute('data-ripple-name'),
    s: state,
    f: root.getAttribute('data-ripple-f') || undefined,
    x: root.getAttribute('data-ripple-x') || undefined,
    ...extras,
  };
}

/** Find first attribute whose name starts with prefix (e.g. ripple:bind). */
export function findAttr(el, prefix) {
  return Array.from(el.attributes).find(
    (a) => a.name === prefix || a.name.startsWith(prefix + '.'),
  );
}

export function parseModifiers(attrName) {
  const parts = attrName.split('.');
  const mods = { live: false, lazy: false, debounce: null };
  if (parts.includes('live')) mods.live = true;
  if (parts.includes('lazy')) mods.lazy = true;
  const di = parts.indexOf('debounce');
  if (di >= 0 && parts[di + 1]) mods.debounce = parseInt(parts[di + 1], 10);
  else if (mods.live) mods.debounce = 150;
  return mods;
}

export function parseInterval(value) {
  if (!value) return 2000;
  if (value.endsWith('ms')) return parseInt(value, 10) || 2000;
  if (value.endsWith('s')) return (parseFloat(value) || 2) * 1000;
  return parseInt(value, 10) || 2000;
}
