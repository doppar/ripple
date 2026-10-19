# Configuration

Publish the config file:

```bash
php pool vendor:publish --tag=ripple
```

Keys below refer to `config/ripple.php` (merged as `ripple.*`).

## Endpoints

| Key | Default | Role |
|-----|---------|------|
| `update_uri` | `/ripple/update` | Action and property updates |
| `lazy_uri` | `/ripple/lazy` | Lazy component hydration |
| `upload_uri` | `/ripple/upload` | Temporary file upload |
| `preview_uri` | `/ripple/preview` | Temporary file preview |

Change these only if your app needs non-default paths. `Ripple::scripts()` emits matching meta tags for the client.

## Component namespace

```php
'component_namespace' => 'App\\Ripple\\',
```

Used when mounting by short name (`ripple('counter')` resolves `App\Ripple\Counter`).

## Full-page layout

```php
'layout' => env('RIPPLE_LAYOUT', 'ripple::layouts.app'),
```

Default Odo layout for `Route::ripple()` when the component has no `#[Layout]` attribute. The layout must `#yield('content')` and include `Ripple::scripts()` in `<head>`.

## Security

```php
'security' => [
    'signing_key' => env('RIPPLE_SIGNING_KEY'),
    'verify_state' => env('RIPPLE_VERIFY_STATE', 'strict'),
],
```

| Option | Meaning |
|--------|---------|
| `signing_key` | HMAC key for state signatures; falls back to `APP_KEY` when null. Prefer a dedicated `RIPPLE_SIGNING_KEY` in production. |
| `verify_state` | `strict` (default) — verify every update; `property-updates` — verify property sync (`t=u`) only, `#[Locked]` properties are still ignored on unsigned actions; `none` — skip (local only). |

## Performance

```php
'performance' => [
    'debounce_default' => env('RIPPLE_DEBOUNCE_DEFAULT', 150),
],
```

Default pause (ms) for `ripple:bind.debounce` when no `.debounce.N` is set.

## Soft navigation

```php
'navigate' => [
    'show_progress_bar' => env('RIPPLE_NAVIGATE_PROGRESS', true),
    'progress_bar_color' => env('RIPPLE_NAVIGATE_PROGRESS_COLOR', '#2299dd'),
],
```

Top progress bar during soft navigations (`ripple:navigate`, soft redirects, `popstate`). See [Navigation](navigation.md#progress-bar).

## File uploads

```php
'file_upload' => [
    'disk' => env('RIPPLE_FILE_DISK', 'local'),
    'temporary_path' => env('RIPPLE_FILE_TEMP_PATH', 'ripple/temp'),
    'max_size' => env('RIPPLE_FILE_MAX_SIZE', 12288), // KB
    'cleanup_after_hours' => env('RIPPLE_FILE_CLEANUP_HOURS', 24),
],
```

See [File uploads](file-uploads.md).

## Environment checklist

```env
RIPPLE_SIGNING_KEY=
RIPPLE_VERIFY_STATE=property-updates
RIPPLE_DEBOUNCE_DEFAULT=150
RIPPLE_NAVIGATE_PROGRESS=true
RIPPLE_NAVIGATE_PROGRESS_COLOR=#2299dd
RIPPLE_FILE_DISK=local
RIPPLE_FILE_TEMP_PATH=ripple/temp
RIPPLE_FILE_MAX_SIZE=12288
RIPPLE_FILE_CLEANUP_HOURS=24
```

Raise PHP `upload_max_filesize` and `post_max_size` above `RIPPLE_FILE_MAX_SIZE` when accepting larger files.
