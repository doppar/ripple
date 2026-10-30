import { parseInterval, payloadFrom, rootOf } from './dom.js';
import { enqueue, send } from './http.js';
import { applyResponse } from './patch.js';

export function bindPolls(root) {
  root.querySelectorAll('[ripple\\:poll]').forEach((el) => {
    if (el.hasAttribute('data-ripple-poll-bound')) return;
    el.setAttribute('data-ripple-poll-bound', 'true');
    const ms = parseInterval(el.getAttribute('ripple:poll'));
    const action = el.getAttribute('ripple:poll.call') || 'refresh';
    const mount = rootOf(el) || root;
    // At most one run of this poll waits behind a request that is already in flight.
    let waiting = false;
    const timer = setInterval(() => {
      // The element was removed (or its component replaced): this poll is over.
      if (!el.isConnected) {
        clearInterval(timer);
        return;
      }
      // Do not stack polls behind a slow request: one waiting run is enough, the rest are dropped.
      // A tick that lands while another request runs is kept and sent right after it, not lost.
      if (waiting) return;
      waiting = true;
      // Silent: no loading UI — interval polls would otherwise flash every tick.
      enqueue(mount, () => {
        waiting = false;
        return send(mount, payloadFrom(mount, { t: 'a', a: action, g: [] })).then((data) => applyResponse(mount, data));
      }).catch(() => {
        waiting = false;
      });
    }, ms);
  });
}
