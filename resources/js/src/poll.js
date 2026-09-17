import { parseInterval, payloadFrom, rootOf } from './dom.js';
import { send } from './http.js';
import { applyResponse } from './patch.js';

export function bindPolls(root) {
  root.querySelectorAll('[ripple\\:poll]').forEach((el) => {
    if (el.hasAttribute('data-ripple-poll-bound')) return;
    el.setAttribute('data-ripple-poll-bound', 'true');
    const ms = parseInterval(el.getAttribute('ripple:poll'));
    const action = el.getAttribute('ripple:poll.call') || 'refresh';
    const mount = rootOf(el) || root;
    setInterval(() => {
      // Silent: no loading UI — interval polls would otherwise flash every tick.
      send(mount, payloadFrom(mount, { t: 'a', a: action, g: [] }))
        .then((data) => applyResponse(mount, data))
        .catch(() => {});
    }, ms);
  });
}
