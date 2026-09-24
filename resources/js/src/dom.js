export function updateUri() {
  return document.querySelector('meta[name="ripple-update"]')?.content || '/ripple/update';
}

export function lazyUri() {
  return document.querySelector('meta[name="ripple-lazy"]')?.content || '/ripple/lazy';
}

export function uploadUri() {
  return document.querySelector('meta[name="ripple-upload"]')?.content || '/ripple/upload';
}

export function previewUri() {
  return document.querySelector('meta[name="ripple-preview"]')?.content || '/ripple/preview';
}

export function defaultDebounce() {
  const n = parseInt(document.querySelector('meta[name="ripple-debounce"]')?.content || '150', 10);
  return Number.isFinite(n) && n >= 0 ? n : 150;
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

  let er;
  try {
    const raw = root.getAttribute('data-ripple-er');
    if (raw) er = JSON.parse(raw);
  } catch (_) {}

  const m = deferredModels(root, state);

  return {
    i: root.getAttribute('data-ripple-id'),
    n: root.getAttribute('data-ripple-name'),
    s: state,
    f: root.getAttribute('data-ripple-f') || undefined,
    x: root.getAttribute('data-ripple-x') || undefined,
    ...(er && typeof er === 'object' && Object.keys(er).length ? { er } : {}),
    ...(Object.keys(m).length ? { m } : {}),
    ...extras,
  };
}

/**
 * Plain ripple:bind values that differ from signed state.
 * They ride along on every request. .live, .lazy, and .debounce sync on their own.
 */
function deferredModels(root, state) {
  const m = {};
  root.querySelectorAll('input, textarea, select').forEach((el) => {
    if (rootOf(el) !== root) return;
    const attr = findAttr(el, 'ripple:bind');
    if (!attr || el.type === 'file') return;
    const mods = parseModifiers(attr.name);
    if (mods.live || mods.lazy || mods.debounce != null) return;
    if (el.type === 'radio' && !el.checked) return;

    const property = attr.value;
    const value = el.type === 'checkbox' ? el.checked : el.value;
    if (Object.prototype.hasOwnProperty.call(state, property) && state[property] === value) return;
    m[property] = value;
  });
  return m;
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
  if (di >= 0) {
    const n = parseInt(parts[di + 1], 10);
    mods.debounce = Number.isFinite(n) ? n : defaultDebounce();
  }
  return mods;
}

export function parseInterval(value) {
  if (!value) return 2000;
  if (value.endsWith('ms')) return parseInt(value, 10) || 2000;
  if (value.endsWith('s')) return (parseFloat(value) || 2) * 1000;
  return parseInt(value, 10) || 2000;
}

/** Parse ripple:call="save" or ripple:call="toggle(3)" / remove('x'). */
export function parseCall(expr) {
  const raw = String(expr || '').trim();
  const m = /^([A-Za-z_][\w]*)\((.*)\)$/s.exec(raw);
  if (!m) return { action: raw, args: [] };

  const inner = m[2].trim();
  if (inner === '') return { action: m[1], args: [] };

  const args = [];
  let buf = '';
  let quote = null;
  for (let i = 0; i < inner.length; i++) {
    const ch = inner[i];
    if (quote) {
      if (ch === quote) quote = null;
      else buf += ch;
      continue;
    }
    if (ch === '"' || ch === "'") {
      quote = ch;
      continue;
    }
    if (ch === ',') {
      args.push(coerceArg(buf.trim()));
      buf = '';
      continue;
    }
    buf += ch;
  }
  if (buf.trim() !== '' || inner.endsWith(',')) args.push(coerceArg(buf.trim()));

  return { action: m[1], args };
}

function coerceArg(s) {
  if (s === '') return '';
  if (s === 'true') return true;
  if (s === 'false') return false;
  if (s === 'null') return null;
  if (/^-?\d+$/.test(s)) return parseInt(s, 10);
  if (/^-?\d+\.\d+$/.test(s)) return parseFloat(s);
  return s;
}
