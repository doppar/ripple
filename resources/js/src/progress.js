/** Top progress bar for soft page navigation. Mounts on <html> so body swaps keep it. */

const SHOW_DELAY_MS = 150;
const ID = 'ripple-progress';

let active = false;
let showTimer = null;
let trickleTimer = null;
let bar = null;

function enabled() {
  const m = document.querySelector('meta[name="ripple-navigate-progress"]');
  if (!m) return true;
  return m.content !== '0' && m.content.toLowerCase() !== 'false';
}

function color() {
  return (
    document.querySelector('meta[name="ripple-navigate-progress-color"]')?.content || '#2299dd'
  );
}

function ensure() {
  if (!document.getElementById(`${ID}-style`)) {
    const style = document.createElement('style');
    style.id = `${ID}-style`;
    style.textContent =
      `#${ID}{pointer-events:none;position:fixed;z-index:2147483647;top:0;left:0;width:100%;height:2px;opacity:0;transition:opacity .15s}` +
      `#${ID}[data-on]{opacity:1}` +
      `#${ID}>i{display:block;height:100%;width:0;background:var(--ripple-progress,#2299dd);box-shadow:0 0 8px var(--ripple-progress,#2299dd);transition:width .2s ease}`;
    document.head.appendChild(style);
  }

  bar = document.getElementById(ID);
  if (!bar) {
    bar = document.createElement('div');
    bar.id = ID;
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML = '<i></i>';
    document.documentElement.appendChild(bar);
  }

  bar.style.setProperty('--ripple-progress', color());
  return bar;
}

function fill() {
  return ensure().firstElementChild;
}

function setWidth(pct) {
  const i = fill();
  if (i) i.style.width = `${pct}%`;
}

function trickle() {
  clearInterval(trickleTimer);
  trickleTimer = setInterval(() => {
    if (!active || !bar) return;
    const i = bar.firstElementChild;
    if (!i) return;
    const w = parseFloat(i.style.width) || 0;
    if (w >= 92) return;
    setWidth(Math.min(92, w + 2 + Math.random() * 6));
  }, 280);
}

export function startProgress() {
  if (!enabled()) return;
  active = true;
  clearTimeout(showTimer);
  clearInterval(trickleTimer);

  showTimer = setTimeout(() => {
    if (!active) return;
    ensure();
    setWidth(0);
    bar.setAttribute('data-on', '');
    requestAnimationFrame(() => setWidth(18));
    trickle();
  }, SHOW_DELAY_MS);
}

export function finishProgress() {
  active = false;
  clearTimeout(showTimer);
  clearInterval(trickleTimer);
  showTimer = null;
  trickleTimer = null;

  if (!bar || !bar.hasAttribute('data-on')) return;

  setWidth(100);
  setTimeout(() => {
    if (!bar) return;
    bar.removeAttribute('data-on');
    setTimeout(() => setWidth(0), 150);
  }, 180);
}
