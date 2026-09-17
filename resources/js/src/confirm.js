export function confirmAction(el) {
  const msg = el.getAttribute('ripple:confirm');
  if (!msg) return true;
  return window.confirm(msg);
}
