# Ripple

Server-driven UI for [Doppar](https://doppar.com).

You write PHP components and Odo views. The browser calls actions; Ripple re-renders on the server and patches only what changed in the DOM.

## Requirements

- PHP 8.5+
- Doppar 4.x

## Install

```bash
composer require doppar/ripple
```

Register the launcher in `runtime/config/app.php`:

```php
'launchers' => [
    App\Launchers\AppLauncher::class,
    Doppar\Ripple\RippleLauncher::class,
],
```

Publish config and client assets (optional; assets are also copied on boot when missing):

```bash
php pool vendor:publish --tag=ripple
php pool vendor:publish --tag=ripple-assets
```

Include the client once in your layout `<head>`:

```odo
[[! ripple_scripts() !]]
```

## Quick start

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

    public function render(): mixed
    {
        return view('ripple.counter');
    }
}
```

```odo
<div>
    <p>Count: [[ $count ]]</p>
    <button type="button" ripple:call="increment">+</button>
</div>
```

Mount it in a page:

```odo
[[! ripple('counter') !]]
```

## Documentation

Full guides live in [`docs/`](docs/README.md):

| Topic | Description |
|-------|-------------|
| [Installation](docs/installation.md) | Setup, assets, layout scripts |
| [Getting started](docs/getting-started.md) | First component end to end |
| [Components](docs/components.md) | State, attributes, render |
| [Directives](docs/directives.md) | `ripple:call`, `ripple:submit`, bind, loading, poll |
| [Lifecycle](docs/lifecycle.md) | `mount`, `updated`, hooks |
| [Validation](docs/validation.md) | Rules and `$errors` |
| [Events](docs/events.md) | Component and browser events |
| [Query string](docs/query-string.md) | URL-synced properties |
| [Pagination](docs/pagination.md) | `HasPagination` trait |
| [Lazy loading](docs/lazy-loading.md) | Deferred mount |
| [File uploads](docs/file-uploads.md) | Temp upload and permanent store |
| [Navigation](docs/navigation.md) | Soft and hard redirects |
| [Configuration](docs/configuration.md) | `config/ripple.php` reference |
| [Testing](docs/testing.md) | `Ripple::test()` and assertions |

