/** Soft page navigation: fetch HTML, swap body, pushState, remount Ripple. */

import { startProgress, finishProgress } from './progress.js';

let latest = 0;
let inflight = null;

/**
 * Soft-navigate to a URL. A newer navigation replaces one that is still loading: the older fetch
 * is aborted and its result is never applied, so the page always matches the last URL asked for
 * (Back right after Forward, or two quick clicks on a link).
 */
export async function softNavigate(url, { push = true } = {}) {
  const id = ++latest;
  if (inflight) inflight.abort();
  const controller = new AbortController();
  inflight = controller;

  const abs = new URL(url, window.location.href);
  startProgress();
  window.dispatchEvent(
    new CustomEvent('ripple:navigating', { detail: { url: abs.href } }),
  );

  try {
    if (abs.origin !== window.location.origin) {
      window.location.assign(abs.href);
      return;
    }

    // mode 'same-origin' makes the fetch fail on a redirect to another origin, so a hop through an
    // open redirect ends in a real browser navigation instead of foreign HTML in this document.
    const res = await fetch(abs.href, {
      headers: {
        Accept: 'text/html',
        'X-Ripple-Navigate': '1',
      },
      credentials: 'same-origin',
      mode: 'same-origin',
      signal: controller.signal,
    });

    if (id !== latest) return;

    const finalUrl = new URL(res.url || abs.href, window.location.href);
    const type = (res.headers.get('content-type') || '').toLowerCase();

    // Only same-origin HTML is swapped in. Anything else (JSON, text/plain, files) is loaded the
    // normal way, where the browser applies its own content-type handling.
    if (!res.ok || finalUrl.origin !== window.location.origin || !type.startsWith('text/html')) {
      window.location.assign(abs.href);
      return;
    }

    const html = await res.text();
    if (id !== latest) return;

    applyDocument(html, push ? finalUrl.href : null);
  } catch (_) {
    // A newer navigation took over; this one was aborted on purpose.
    if (id !== latest) return;
    window.location.assign(url);
  } finally {
    if (id === latest) {
      inflight = null;
      finishProgress();
    }
  }
}

/** @param {string|null} pushUrl null = don't push (popstate) */
export function applyDocument(html, pushUrl) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  document.title = doc.title;

  const next = document.importNode(doc.body, true);
  document.body.replaceWith(next);

  if (pushUrl != null) {
    const u = new URL(pushUrl, window.location.href);
    history.pushState({ rippleSpa: true }, '', u.pathname + u.search + u.hash);
  }

  window.dispatchEvent(new CustomEvent('ripple:navigated', { detail: { url: window.location.href } }));
}

export function hardNavigate(url) {
  window.location.assign(url);
}

export function handleRedirect(rd) {
  if (!rd?.u) return false;
  if (rd.spa === false) {
    hardNavigate(rd.u);
  } else {
    softNavigate(rd.u);
  }
  return true;
}
