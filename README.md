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

Optional config publish:

```bash
php pool vendor:publish --tag=ripple-config
```

Load the client in your layout:

```php
[[! \Doppar\Ripple\Facade\Ripple::scripts() !]]
```

## Example

```php
<?php

namespace App\Ripple;

use Doppar\Ripple\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<HTML
            <div>
                <p>Count: {$this->count}</p>
                <button type="button" ripple-call="increment">+</button>
            </div>
        HTML;
    }
}
```

Mount:

```php
[[! \Doppar\Ripple\Facade\Ripple::mount(\App\Ripple\Counter::class) !]]
```

### Attributes

| Attribute | Purpose |
|-----------|---------|
| `data-ripple` | Root mount (added automatically) |
| `data-ripple-id` / `data-ripple-name` | Instance identity |
| `ripple-call="method"` | Call a component method (default event: `click`) |
| `ripple-on="submit"` | Event that triggers `ripple-call` |
| `ripple-bind="prop"` | Sync an input value to a component property |

## Update protocol

`POST /ripple/update`

Keys stay short on the wire (`i` id, `n` name, `a` action, `g` args, `s` state, `d` deltas; patch `o` op, `p` path, `v` value).

Request:

```json
{ "i": "...", "n": "App\\Ripple\\Counter", "a": "increment", "g": [], "s": { "count": 0 } }
```

Response:

```json
{ "i": "...", "n": "App\\Ripple\\Counter", "d": [{ "o": "t", "p": "0.0", "v": "Count: 1" }], "s": { "count": 1 } }
```

Op codes: `t` setText, `u` updateAttrs, `r` replace, `i` insert, `x` remove, `m` reorder.

## Flow

```
Browser (ripple-call / ripple-bind)
  -> POST /ripple/update
  -> ComponentManager (hydrate, action, render)
  -> DiffEngine (old VDOM vs new VDOM)
  -> JSON deltas + state
  -> Client applies patches
```

## License

MIT

## Tests

PHPUnit. Suites live under `tests/Unit`, `tests/Feature`, and `tests/Integration`.

```bash
composer install
composer test
```

## Client assets

Source lives in `resources/js/src`. Build (esbuild, minified IIFE) writes `resources/dist/ripple.min.js`.

```bash
npm install
npm run build
```

Run `npm run build` before tagging a release or publishing. Publish / sync uses the dist file (`php pool vendor:publish --tag=ripple-assets`).
