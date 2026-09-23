# Installation

## Composer

```bash
composer require doppar/ripple
```

## Register the launcher

In `runtime/config/app.php`, add `RippleLauncher` to `launchers`:

```php
'launchers' => [
    App\Launchers\AppLauncher::class,
    Doppar\Ripple\RippleLauncher::class,
],
```

Routes for update, lazy load, upload, and preview are registered automatically.

## Publish config

```bash
php pool vendor:publish --tag=ripple
```

This copies `config/ripple.php` into your app (typically under `runtime/config/`). See [Configuration](configuration.md).

## Client assets

Ripple serves a browser script at `/vendor/ripple/ripple.min.js`. On boot, the package copies it into `public/vendor/ripple/` when missing or outdated.

To force a publish:

```bash
php pool vendor:publish --tag=ripple-assets
```

## Load the client

Put scripts in your **layout** `<head>`, not only on individual pages. Soft navigation swaps the body and keeps the head, so the client must already be loaded there.

```odo
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>#yield('title')</title>
    [[! \Doppar\Ripple\Facade\Ripple::scripts() !]]
</head>
<body>
    #yield('content')
</body>
</html>
```

`Ripple::scripts()` emits CSRF and Ripple meta tags plus the deferred script tag.

## Verify

1. Create a component: `php pool make:ripple Hello`
2. Mount it on a page with `ripple('hello')`
3. Click a `ripple:call` button and confirm the UI updates without a full page reload

Next: [Getting started](getting-started.md)
