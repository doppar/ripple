import { payloadFrom, rootOf } from './dom.js';
import { send } from './http.js';
import { applyResponse } from './patch.js';

export function bindListeners() {
  document.addEventListener(
    'click',
    function (e) {
      const btn = e.target.closest('[ripple-call]');
      if (!btn) return;
      const on = (btn.getAttribute('ripple-on') || 'click').toLowerCase();
      if (on !== 'click') return;
      const root = rootOf(btn);
      if (!root) return;
      e.preventDefault();
      send(root, payloadFrom(root, btn.getAttribute('ripple-call'), []))
        .then((data) => applyResponse(root, data))
        .catch(console.error);
    },
    true,
  );

  document.addEventListener(
    'submit',
    function (e) {
      const form = e.target.closest('[ripple-call]');
      if (!form) return;
      const on = (form.getAttribute('ripple-on') || 'click').toLowerCase();
      if (on !== 'submit') return;
      const root = rootOf(form);
      if (!root) return;
      e.preventDefault();
      send(root, payloadFrom(root, form.getAttribute('ripple-call'), []))
        .then((data) => applyResponse(root, data))
        .catch(console.error);
    },
    true,
  );

  document.addEventListener(
    'input',
    function (e) {
      const el = e.target.closest('[ripple-bind]');
      if (!el) return;
      const root = rootOf(el);
      if (!root) return;
      const prop = el.getAttribute('ripple-bind');
      const override = {};
      override[prop] = el.value;
      send(root, payloadFrom(root, null, [], override))
        .then((data) => applyResponse(root, data))
        .catch(console.error);
    },
    true,
  );
}
