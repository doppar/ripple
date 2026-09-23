import { findAttr, parseCall, payloadFrom, rootOf } from './dom.js';
import { enqueue, send } from './http.js';
import { confirmAction } from './confirm.js';
import { startLoading } from './loading.js';
import { applyResponse } from './patch.js';
import { bindModels } from './bind.js';
import { bindPolls } from './poll.js';
import { bindLazy } from './lazy.js';
import { softNavigate } from './navigate.js';

let documentBound = false;

/** Read current DOM values for every ripple:bind control under the mount. */
function boundValues(root) {
  const m = {};
  root.querySelectorAll('input, textarea, select').forEach((el) => {
    if (rootOf(el) !== root) return;
    const attr = findAttr(el, 'ripple:bind');
    if (!attr || el.type === 'file') return;
    if (el.type === 'radio' && !el.checked) return;
    if (el.type === 'checkbox') {
      m[attr.value] = el.checked;
      return;
    }
    m[attr.value] = el.value;
  });
  return m;
}

/**
 * @param {Element} root
 * @param {string} action
 * @param {unknown[]} args
 * @param {string|null} event Name of the event being delivered to a #[On] listener, if any
 */
export function runCall(root, action, args = [], event = null) {
  startLoading(root);
  return enqueue(root, () => {
    const m = boundValues(root);
    const extras = { t: 'a', a: action, g: args, ...(event ? { ev: event } : {}) };
    if (Object.keys(m).length) extras.m = m;
    return send(root, payloadFrom(root, extras)).then((data) => applyResponse(root, data, runCall));
  });
}

/** Bind models/polls/lazy on every [data-ripple] under scope (safe after SPA body swap). */
export function mountRippleRoots(scope = document) {
  scope.querySelectorAll('[data-ripple]').forEach((root) => {
    if (root.hasAttribute('data-ripple-mounted')) return;
    root.setAttribute('data-ripple-mounted', '1');
    bindModels(root);
    bindPolls(root);
    bindLazy(root);
    root.querySelectorAll('[ripple\\:loading]').forEach((el) => {
      el.hidden = true;
      el.style.display = 'none';
    });
  });
}

export function bindListeners() {
  if (!documentBound) {
    documentBound = true;

    document.addEventListener(
      'click',
      (e) => {
        const link = e.target.closest('a[ripple\\:navigate]');
        if (link && shouldSoftNavigate(e, link)) {
          e.preventDefault();
          softNavigate(link.href).catch(() => {});
          return;
        }

        const btn = e.target.closest('[ripple\\:call]');
        if (!btn) return;
        const on = (btn.getAttribute('ripple:on') || 'click').toLowerCase();
        if (on !== 'click') return;
        const root = rootOf(btn);
        if (!root) return;
        if (!confirmAction(btn)) return;
        e.preventDefault();
        const { action, args } = parseCall(btn.getAttribute('ripple:call'));
        runCall(root, action, args).catch(() => {});
      },
      true,
    );

    document.addEventListener(
      'submit',
      (e) => {
        const form = e.target.closest('[ripple\\:call]');
        if (!form) return;
        const on = (form.getAttribute('ripple:on') || 'click').toLowerCase();
        if (on !== 'submit') return;
        const root = rootOf(form);
        if (!root) return;
        if (!confirmAction(form)) return;
        e.preventDefault();
        const { action, args } = parseCall(form.getAttribute('ripple:call'));
        runCall(root, action, args).catch(() => {});
      },
      true,
    );

    window.addEventListener('popstate', () => {
      softNavigate(window.location.href, { push: false });
    });

    window.addEventListener('ripple:navigated', () => {
      mountRippleRoots(document);
    });
  }

  mountRippleRoots(document);
}

/** @param {MouseEvent} e @param {HTMLAnchorElement} link */
function shouldSoftNavigate(e, link) {
  if (e.defaultPrevented || e.button !== 0) return false;
  if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;

  const target = (link.getAttribute('target') || '').toLowerCase();
  if (target !== '' && target !== '_self') return false;
  if (link.hasAttribute('download')) return false;

  const href = link.getAttribute('href');
  if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;

  let url;
  try {
    url = new URL(link.href, window.location.href);
  } catch (_) {
    return false;
  }

  if (url.origin !== window.location.origin) return false;
  if (
    url.pathname === window.location.pathname &&
    url.search === window.location.search &&
    url.hash !== ''
  ) {
    return false;
  }

  return true;
}
