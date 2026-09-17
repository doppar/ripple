# Ripple

Server-driven UI for [Doppar](https://doppar.com). Components live in PHP. The browser sends actions; the server re-renders and replies with DOM patches from a Virtual DOM diff (delta rendering).

Early project. APIs can change.

## Install

```bash
composer require doppar/ripple
```

Register the launcher in `runtime/config/app.php`:

```php
"launchers" => [
    App\Launchers\AppLauncher::class,
    Doppar\Ripple\RippleLauncher::class,
],
```

Optional:

```bash
php pool vendor:publish --tag=ripple-config
php pool vendor:publish --tag=ripple-assets
```

Load the client:

```php
[[! \Doppar\Ripple\Facade\Ripple::scripts() !]]
```

## Example

```bash
php pool make:ripple Counter
```

```php
<?php

namespace App\Ripple;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

class Counter extends Component
{
    public int $count = 0;

    #[Invokable]
    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<HTML
            <div>
                <p>Count: {$this->count}</p>
                <button type="button" ripple:call="increment">+</button>
            </div>
        HTML;
    }
}
```

```php
[[! \Doppar\Ripple\Facade\Ripple::mount(\App\Ripple\Counter::class) !]]
```

### Directives

| Attribute | Purpose |
|-----------|---------|
| `ripple:call="method"` | Call an `#[Invokable]` method (default event: click) |
| `ripple:on="submit"` | Event that triggers `ripple:call` |
| `ripple:bind` / `.live` / `.lazy` / `.debounce.N` | Sync input to a public property |
| `ripple:loading` | Shown while a request is in flight |
| `ripple:loading.target` | Disabled / busy while loading |
| `ripple:poll="2s"` | Poll interval (HTTP) |
| `ripple:poll.call="tick"` | Method polled |
| `ripple:confirm="…"` | Confirm before call |

Root mount uses `data-ripple` (injected by `mount()`).

## Update protocol

`POST /ripple/update` uses short keys (`i` id, `n` name, `t` type, `a` action, `g` args, `s` state, `p`/`v` prop update, `d` deltas, `e` validation errors, `rd` redirect).

## Client assets

```bash
npm install
npm run build
```

Writes `resources/dist/ripple.min.js`.

## Tests

```bash
composer install
composer test
```

## License

MIT
