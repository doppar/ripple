const BUSY = 'ripple-busy';

export function startLoading(root) {
  root.classList.add(BUSY);
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
  root.classList.remove(BUSY);
  root.querySelectorAll('[ripple\\:loading]').forEach((el) => {
    el.hidden = true;
    el.style.display = 'none';
  });
  root.querySelectorAll('[ripple\\:loading\\.target]').forEach((el) => {
    el.removeAttribute('disabled');
    el.classList.remove(BUSY);
  });
}
