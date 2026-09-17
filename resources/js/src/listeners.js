import { parseCall, payloadFrom, rootOf } from './dom.js';
import { send } from './http.js';
import { confirmAction } from './confirm.js';
import { startLoading, stopLoading } from './loading.js';
import { applyResponse } from './patch.js';
import { bindModels } from './bind.js';
import { bindPolls } from './poll.js';
import { bindLazy } from './lazy.js';
import { softNavigate } from './navigate.js';

let documentBound = false;

export function runCall(root, action, args = []) {
  startLoading(root);
  return send(root, payloadFrom(root, { t: 'a', a: action, g: args }))
    .then((data) => applyResponse(root, data, runCall))
    .finally(() => stopLoading(root));
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
