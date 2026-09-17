import { lazyUri, payloadFrom } from './dom.js';
import { send } from './http.js';
import { applyResponse } from './patch.js';

export function bindLazy(root) {
  if (!root.hasAttribute('data-ripple-lazy')) return;
  if (root.hasAttribute('data-ripple-lazy-loading')) return;
  root.setAttribute('data-ripple-lazy-loading', 'true');

  let params = {};
  try {
    params = JSON.parse(root.getAttribute('data-ripple-params') || '{}');
  } catch (_) {}

  const body = {
    i: root.getAttribute('data-ripple-id'),
    n: root.getAttribute('data-ripple-name'),
    g: params,
  };

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (csrf) headers['X-CSRF-TOKEN'] = csrf;

  fetch(lazyUri(), {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
    credentials: 'same-origin',
  })
    .then((r) => r.json())
    .then((data) => {
      root.innerHTML = '';
      applyResponse(root, data);
      if (data.s) root.setAttribute('data-ripple-state', JSON.stringify(data.s));
      if (data.f) root.setAttribute('data-ripple-f', data.f);
      if (data.x) root.setAttribute('data-ripple-x', data.x);
      root.removeAttribute('data-ripple-lazy');
      root.removeAttribute('data-ripple-lazy-loading');
    })
    .catch(console.error);
}
