const BUSY = 'ripple-busy';

/**
 * Show [ripple:loading] / disable [ripple:loading.target] only.
 * Do not dim the whole [data-ripple] root — that causes visible blink on every update.
 */
export function startLoading(root) {
  root.setAttribute('data-ripple-pending', '');
  root.querySelectorAll('[ripple\\:loading]').forEach((el) => {
    el.hidden = false;
    el.style.display = '';
  });
  root.querySelectorAll('[ripple\\:loading\\.target]').forEach((el) => {
    el.setAttribute('disabled', 'disabled');
    el.classList.add(BUSY);
  });
}

export function stopLoading(root) {
  root.removeAttribute('data-ripple-pending');
  root.querySelectorAll('[ripple\\:loading]').forEach((el) => {
    el.hidden = true;
    el.style.display = 'none';
  });
  root.querySelectorAll('[ripple\\:loading\\.target]').forEach((el) => {
    el.removeAttribute('disabled');
    el.classList.remove(BUSY);
  });
}
