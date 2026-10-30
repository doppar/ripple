/** Soft page navigation: fetch HTML, swap body, pushState, remount Ripple. */

import { startProgress, finishProgress } from './progress.js';

let navigating = false;

export async function softNavigate(url, { push = true } = {}) {
  if (navigating) return;
  navigating = true;

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
    });

    const finalUrl = new URL(res.url || abs.href, window.location.href);
    const type = (res.headers.get('content-type') || '').toLowerCase();

    // Only same-origin HTML is swapped in. Anything else (JSON, text/plain, files) is loaded the
    // normal way, where the browser applies its own content-type handling.
    if (!res.ok || finalUrl.origin !== window.location.origin || !type.startsWith('text/html')) {
      window.location.assign(abs.href);
      return;
    }

    const html = await res.text();
    applyDocument(html, push ? finalUrl.href : null);
  } catch (_) {
    window.location.assign(url);
  } finally {
    finishProgress();
    navigating = false;
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
