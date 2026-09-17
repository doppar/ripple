let overlay;

export function showError(payload, status) {
  const message = payload?.message || 'Something went wrong.';
  const errors = payload?.errors;
  const detail =
    errors && typeof errors === 'object' && (errors.file || errors.trace)
      ? `\n\n${errors.file || ''}:${errors.line || ''}\n${errors.trace || ''}`
      : '';

  if (!overlay) {
    overlay = document.createElement('div');
    overlay.setAttribute('data-ripple-error', '');
    overlay.style.cssText =
      'position:fixed;inset:0;z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding:1.5rem;background:rgba(0,0,0,.45);font-family:ui-sans-serif,system-ui,sans-serif';
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) hideError();
    });
    document.body.appendChild(overlay);
  }

  overlay.innerHTML = `
    <div role="alert" style="max-width:40rem;width:100%;margin-top:10vh;background:#18181b;color:#fafafa;border-radius:.75rem;padding:1rem 1.25rem;box-shadow:0 20px 40px rgba(0,0,0,.35)">
      <div style="display:flex;justify-content:space-between;gap:1rem;align-items:start">
        <strong style="font-size:.95rem">Ripple error${status ? ' (' + status + ')' : ''}</strong>
        <button type="button" data-ripple-error-close style="background:transparent;border:0;color:#a1a1aa;cursor:pointer;font-size:1.25rem;line-height:1">&times;</button>
      </div>
      <pre style="margin:.75rem 0 0;white-space:pre-wrap;word-break:break-word;font-size:.85rem;color:#e4e4e7;font-family:ui-monospace,Menlo,Consolas,monospace">${escapeHtml(message + detail)}</pre>
    </div>
  `;
  overlay.querySelector('[data-ripple-error-close]')?.addEventListener('click', hideError);
  overlay.style.display = 'flex';
}

export function hideError() {
  if (overlay) overlay.style.display = 'none';
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
