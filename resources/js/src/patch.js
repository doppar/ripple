import { contentRoot } from './dom.js';

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

/**
 * Apply compact delta ops (o/p/v/h/a/x) to the mounted component tree.
 * ponytail: minimal applicator (t/u/r/x/i); upgrade path: keyed reorder.
 */
export function applyPatches(mount, patches) {
  const root = contentRoot(mount);

  for (const p of patches || []) {
    const target = nodeAt(root, p.p);

    switch (p.o) {
      case 't':
        if (target) target.textContent = p.v ?? '';
        break;
      case 'u':
        if (target && target.nodeType === 1 && p.a) {
          for (const [k, v] of Object.entries(p.a)) {
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

export function applyResponse(root, data) {
  applyPatches(root, data.d);
  if (data.s) root.setAttribute('data-ripple-state', JSON.stringify(data.s));
}
