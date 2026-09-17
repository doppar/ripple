/**
 * Ripple client stub. Listens for ripple-call / ripple-bind,
 * POSTs intents, applies returned delta patches.
 * Wire keys stay short (i/n/a/g/s, d, o/p/v) to keep payloads small.
 * ponytail: patch applicator is minimal (t/u/r/x/i);
 * upgrade path: keyed reorder + safer insert before production use.
 */
(function () {
  const UPDATE_URI = document.querySelector('meta[name="ripple-update"]')?.content
    || '/ripple/update';

  function rootOf(el) {
    return el.closest('[data-ripple]');
  }

  function payloadFrom(root, action, args, stateOverride) {
    let state = {};
    try {
      state = JSON.parse(root.getAttribute('data-ripple-state') || '{}');
    } catch (_) {}
    if (stateOverride) Object.assign(state, stateOverride);

    return {
      i: root.getAttribute('data-ripple-id'),
      n: root.getAttribute('data-ripple-name'),
      a: action,
      g: args || [],
      s: state,
    };
  }

  async function send(root, body) {
    const res = await fetch(UPDATE_URI, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
      credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Ripple update failed: ' + res.status);
    return res.json();
  }

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

  function applyPatches(root, patches) {
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

  function applyResponse(root, data) {
    applyPatches(root, data.d);
    if (data.s) root.setAttribute('data-ripple-state', JSON.stringify(data.s));
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[ripple-call]');
    if (!btn) return;
    const on = (btn.getAttribute('ripple-on') || 'click').toLowerCase();
    if (on !== 'click') return;
    const root = rootOf(btn);
    if (!root) return;
    e.preventDefault();
    const action = btn.getAttribute('ripple-call');
    send(root, payloadFrom(root, action, [])).then((data) => {
      applyResponse(root, data);
    }).catch(console.error);
  }, true);

  document.addEventListener('submit', function (e) {
    const form = e.target.closest('[ripple-call]');
    if (!form) return;
    const on = (form.getAttribute('ripple-on') || 'click').toLowerCase();
    if (on !== 'submit') return;
    const root = rootOf(form);
    if (!root) return;
    e.preventDefault();
    send(root, payloadFrom(root, form.getAttribute('ripple-call'), [])).then((data) => {
      applyResponse(root, data);
    }).catch(console.error);
  }, true);

  document.addEventListener('input', function (e) {
    const el = e.target.closest('[ripple-bind]');
    if (!el) return;
    const root = rootOf(el);
    if (!root) return;
    const prop = el.getAttribute('ripple-bind');
    const override = {};
    override[prop] = el.value;
    send(root, payloadFrom(root, null, [], override)).then((data) => {
      applyResponse(root, data);
    }).catch(console.error);
  }, true);

  window.Ripple = { send, applyPatches };
})();
