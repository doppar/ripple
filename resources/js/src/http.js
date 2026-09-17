import { updateUri } from './dom.js';

export async function send(_root, body) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (csrf) headers['X-CSRF-TOKEN'] = csrf;

  const res = await fetch(updateUri(), {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });

  if (!res.ok) {
    throw new Error('Ripple update failed: ' + res.status);
  }

  return res.json();
}
