# Navigation

After an action you can send the browser to another URL. Ripple supports soft (SPA-style) and hard redirects.

## Soft redirect (default)

```php
#[Invokable]
public function finish(): void
{
    $this->redirect('/ripple/orders');
    // same as: $this->redirect('/ripple/orders', true);
}
```

The client:

1. Fetches the target HTML
2. Replaces `document.body`
3. Updates the URL with `pushState`
4. Remounts Ripple roots on the new page

Scripts and meta tags in the **layout `<head>`** stay loaded. Put `Ripple::scripts()` there. See [Installation](installation.md#load-the-client).

## Hard redirect

Full page load:

```php
$this->redirect('/login', false);
```

Use this for logout, external hand-offs, or when the next page must boot from scratch.

## Browser history

Back and forward trigger the same soft navigation path (`popstate`). Same-origin only; other origins fall back to a hard assign.

## Manual client navigation

After scripts load:

```js
window.Ripple.softNavigate('/ripple/counter');
window.Ripple.hardNavigate('/ripple');
```

## Checklist for soft nav

- [ ] `Ripple::scripts()` in the shared layout head
- [ ] Target routes return full HTML documents (normal Doppar views)
- [ ] Page-specific head scripts that must survive a soft entry should live in the layout, or accept that they only run on a hard load

Next: [Configuration](configuration.md)
