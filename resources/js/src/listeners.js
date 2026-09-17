import { payloadFrom, rootOf } from './dom.js';
import { send } from './http.js';
import { confirmAction } from './confirm.js';
import { startLoading, stopLoading } from './loading.js';
import { applyResponse } from './patch.js';
import { bindModels } from './bind.js';
import { bindPolls } from './poll.js';
import { bindLazy } from './lazy.js';

export function runCall(root, action, args = []) {
  startLoading(root);
  return send(root, payloadFrom(root, { t: 'a', a: action, g: args }))
    .then((data) => applyResponse(root, data, runCall))
    .finally(() => stopLoading(root));
}

export function bindListeners() {
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
      runCall(root, btn.getAttribute('ripple:call'), []).catch(() => {});
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
      runCall(root, form.getAttribute('ripple:call'), []).catch(() => {});
    },
    true,
  );

  document.querySelectorAll('[data-ripple]').forEach((root) => {
    bindModels(root);
    bindPolls(root);
    bindLazy(root);
    root.querySelectorAll('[ripple\\:loading]').forEach((el) => {
      el.hidden = true;
      el.style.display = 'none';
    });
  });
}
