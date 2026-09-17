/** Soft page navigation: fetch HTML, swap body, pushState, remount Ripple. */

let navigating = false;

export async function softNavigate(url, { push = true } = {}) {
  if (navigating) return;
  navigating = true;
  try {
    const abs = new URL(url, window.location.href);
    if (abs.origin !== window.location.origin) {
      window.location.assign(abs.href);
      return;
    }

    const res = await fetch(abs.href, {
      headers: {
        Accept: 'text/html',
        'X-Ripple-Navigate': '1',
      },
      credentials: 'same-origin',
    });

    if (!res.ok) {
      window.location.assign(abs.href);
      return;
    }

    const html = await res.text();
    applyDocument(html, push ? (res.url || abs.href) : null);
  } catch (_) {
    window.location.assign(url);
  } finally {
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
