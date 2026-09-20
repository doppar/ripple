# Navigation

Ripple supports soft (SPA-style) and hard navigation: redirects from actions, and link clicks via `ripple:navigate`.

## Soft links: `ripple:navigate`

```odo
<a href="/ripple/counter" ripple:navigate>Counter</a>
```

The client fetches the target HTML, replaces `document.body`, updates the URL with `pushState`, and remounts Ripple roots. Same-origin only.

See [Directives](directives.md#spa-links-ripplenavigate).

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

## Full-page routes: `Route::ripple()`

Register a component as the whole page (Livewire-style `Route::livewire`):

```php
use Phaseolies\Support\Facades\Route;
use App\Ripple\Counter;
use Doppar\Ripple\Attributes\Layout;
use Doppar\Ripple\Attributes\Title;

Route::ripple('/counter', Counter::class)->name('counter');
```

```php
#[Layout('demos.layout')]   // optional — defaults to config('ripple.layout')
#[Title('Counter')]         // optional — defaults to short class name
class Counter extends Component
{
    public function render(): mixed
    {
        return view('ripple.counter'); // component body only
    }
}
```

The layout must `#yield('content')` (and usually `#yield('title')`) and include `Ripple::scripts()` in `<head>`. Package default layout: `ripple::layouts.app`.

Island mounts (`Ripple::mount()` inside a view) still work; use `Route::ripple()` when the component *is* the page.

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
- [ ] Target routes return full HTML documents (normal Doppar views or `Route::ripple()`)
- [ ] Page-specific head scripts that must survive a soft entry should live in the layout, or accept that they only run on a hard load

Next: [Configuration](configuration.md)
