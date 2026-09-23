import { updateUri } from './dom.js';
import { stopLoading } from './loading.js';

async function readBody(res) {
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    return { kind: 'json', data: await res.json().catch(() => ({})) };
  }
  return { kind: 'html', data: await res.text() };
}

/** Soft validation / wire payload (apply patches), not a fatal app error. */
function isWirePayload(data) {
  return data && typeof data === 'object' && ('d' in data || 's' in data || 'e' in data);
}

/** Replace the page with Doppar's HTML error response (debug or 500). */
function showServerErrorPage(html) {
  document.open();
  document.write(html);
  document.close();
}

const tails = new WeakMap();
const inFlight = new WeakMap();

/**
 * Run `task` after every earlier task for the same component root has settled.
 *
 * Each update carries the signed state the root held when it was built, and the response
 * replaces that state. Two updates in flight at once would both start from the same state and
 * the last response would silently discard the first one's changes, so they run one at a time
 * and `task` builds its payload only when its turn comes.
 *
 * @template T
 * @param {Element} root
 * @param {() => Promise<T>} task
 * @returns {Promise<T>}
 */
export function enqueue(root, task) {
  inFlight.set(root, (inFlight.get(root) || 0) + 1);

  const run = (tails.get(root) || Promise.resolve()).then(async () => {
    try {
      return await task();
    } finally {
      const left = (inFlight.get(root) || 1) - 1;
      inFlight.set(root, left);
      // The task that started the indicator may have finished earlier. A later
      // silent update must still be the one that turns it off.
      if (left <= 0) stopLoading(root);
    }
  });

  tails.set(root, run.catch(() => {}));

  return run;
}

/** True while any queued update for this root has not finished. */
export function isBusy(root) {
  return (inFlight.get(root) || 0) > 0;
}

export async function send(_root, body) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'text/html, application/json',
  };

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (csrf) headers['X-CSRF-TOKEN'] = csrf;

  const res = await fetch(updateUri(), {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });

  const bodyRead = await readBody(res);

  if (bodyRead.kind === 'json' && isWirePayload(bodyRead.data)) {
    return bodyRead.data;
  }

  if (!res.ok) {
    if (bodyRead.kind === 'html') {
      showServerErrorPage(bodyRead.data);
      throw new Error('Ripple update failed: ' + res.status);
    }
    // JSON error without wire shape — let Doppar-style payload surface via throw
    const err = new Error(bodyRead.data.message || 'Ripple update failed: ' + res.status);
    err.payload = bodyRead.data;
    err.status = res.status;
    throw err;
  }

  if (bodyRead.kind !== 'json') {
    throw new Error('Unexpected Ripple response');
  }

  return bodyRead.data;
}

export async function sendForm(url, formData) {
  const headers = {
    Accept: 'text/html, application/json',
  };
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (csrf) headers['X-CSRF-TOKEN'] = csrf;

  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: formData,
    credentials: 'same-origin',
  });
  const bodyRead = await readBody(res);
  if (!res.ok) {
    if (bodyRead.kind === 'html') {
      showServerErrorPage(bodyRead.data);
      throw new Error('Upload failed');
    }
    throw new Error(bodyRead.data.message || 'Upload failed');
  }
  return bodyRead.kind === 'json' ? bodyRead.data : {};
}
