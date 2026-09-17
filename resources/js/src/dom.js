export function updateUri() {
  return document.querySelector('meta[name="ripple-update"]')?.content || '/ripple/update';
}

export function rootOf(el) {
  return el.closest('[data-ripple]');
}

export function contentRoot(mount) {
  // Server diffs the component render HTML, which is the first child of [data-ripple].
  return mount.firstElementChild || mount;
}

export function payloadFrom(root, action, args, stateOverride) {
  let state = {};
  try {
    state = JSON.parse(root.getAttribute('data-ripple-state') || '{}');
  } catch (_) {}
  if (stateOverride) Object.assign(state, stateOverride);

  return {
    i: root.getAttribute('data-ripple-id'),
    n: root.getAttribute('data-ripple-name'),
    a: action,
    g: args || [],
    s: state,
  };
}
