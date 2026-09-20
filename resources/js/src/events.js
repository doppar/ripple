/** Handle response `ev` (component) and `be` (browser) events. */
export function handleEvents(sourceRoot, data, callAction) {
  if (Array.isArray(data.be)) {
    for (const ev of data.be) {
      window.dispatchEvent(new CustomEvent(ev.name, { detail: ev.data }));
    }
  }

  if (!Array.isArray(data.ev) || !callAction) return;

  for (const ev of data.ev) {
    document.querySelectorAll('[data-ripple]').forEach((root) => {
      if (ev.self && root !== sourceRoot) return;
      if (Array.isArray(ev.to) && ev.to.length) {
        const id = root.getAttribute('data-ripple-id');
        const name = root.getAttribute('data-ripple-name');
        if (!ev.to.includes(id) && !ev.to.includes(name)) return;
      }
      let listeners = {};
      try {
        listeners = JSON.parse(root.getAttribute('data-ripple-el') || '{}');
      } catch {
        listeners = {};
      }
      const methods = listeners[ev.name] || [];
      for (const method of methods) {
        callAction(root, method, ev.params || [], ev.name);
      }
    });
  }
}
