import { contentRoot } from './dom.js';
import { handleEvents } from './events.js';
import { hydrateBoundFields } from './hydrate.js';
import { handleRedirect } from './navigate.js';
import { bindPolls } from './poll.js';

function nodeAt(root, path) {
  if (path === '' || path == null) return root;
  let node = root;
  for (const seg of String(path).split('.')) {
    const i = parseInt(seg, 10);
    if (!node || !node.childNodes[i]) return null;
    node = node.childNodes[i];
  }
  return node;
}

export function applyPatches(mount, patches) {
  const root = contentRoot(mount);
  const active = document.activeElement;

  for (const p of patches || []) {
    const target = nodeAt(root, p.p);
    switch (p.o) {
      case 't':
        if (target) target.textContent = p.v ?? '';
        break;
      case 'u':
        if (target && target.nodeType === 1 && p.a) {
          for (const [k, v] of Object.entries(p.a)) {
            if (
              target === active &&
              (k === 'value' || k === 'checked') &&
              (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT')
            ) {
              continue;
            }
            if (v === null || v === false) target.removeAttribute(k);
            else target.setAttribute(k, v === true ? '' : String(v));
          }
        }
        break;
      case 'r':
        if (target && p.h != null) {
          const tmp = document.createElement('template');
          tmp.innerHTML = p.h;
          target.replaceWith(tmp.content.firstChild || document.createTextNode(''));
        }
        break;
      case 'x':
        if (target) target.remove();
        break;
      case 'i':
        if (target && p.h != null) {
          const tmp = document.createElement('template');
          tmp.innerHTML = p.h;
          const child = tmp.content.firstChild;
          const at = p.x ?? target.childNodes.length;
          if (child) {
            if (at >= target.childNodes.length) target.appendChild(child);
            else target.insertBefore(child, target.childNodes[at]);
          }
        }
        break;
      default:
        break;
    }
  }
}

export function applyResponse(root, data, callAction) {
  if (handleRedirect(data.rd)) {
    return;
  }

  if (data.h && root.hasAttribute('data-ripple-lazy')) {
    root.removeAttribute('data-ripple-lazy');
    const wrap = document.createElement('div');
    wrap.innerHTML = data.h;
    while (wrap.firstChild) root.appendChild(wrap.firstChild);
  }

  applyPatches(root, data.d);
  // A patch may have inserted or replaced [ripple:poll] elements; already-bound ones are skipped.
  bindPolls(root);
  if (data.s) root.setAttribute('data-ripple-state', JSON.stringify(data.s));
  if (data.f) root.setAttribute('data-ripple-f', data.f);
  if (data.x) root.setAttribute('data-ripple-x', data.x);

  if (data.e && typeof data.e === 'object' && Object.keys(data.e).length) {
    root.setAttribute('data-ripple-er', JSON.stringify(data.e));
  } else {
    root.removeAttribute('data-ripple-er');
  }

  if (data.qs && typeof data.qs === 'object') {
    const url = new URL(window.location.href);
    for (const [k, v] of Object.entries(data.qs)) {
      if (v == null || v === '') url.searchParams.delete(k);
      else url.searchParams.set(k, String(v));
    }
    const qs = url.searchParams.toString();
    history.replaceState({}, '', url.pathname + (qs ? '?' + qs : '') + url.hash);
  }

  handleEvents(root, data, callAction);
  hydrateBoundFields(root);
}
