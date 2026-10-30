<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Update Endpoint
    |--------------------------------------------------------------------------
    |
    | URI that receives component update requests (actions, property sync).
    | The client posts short-key JSON payloads here.
    |
    */

    'update_uri' => '/ripple/update',

    /*
    |--------------------------------------------------------------------------
    | Lazy Load Endpoint
    |--------------------------------------------------------------------------
    |
    | URI used to hydrate components marked with #[Lazy] after the placeholder
    | mount is shown in the browser.
    |
    */

    'lazy_uri' => '/ripple/lazy',

    /*
    |--------------------------------------------------------------------------
    | File Upload Endpoint
    |--------------------------------------------------------------------------
    |
    | URI that accepts temporary file uploads bound via ripple:bind on
    | <input type="file"> elements.
    |
    */

    'upload_uri' => '/ripple/upload',

    /*
    |--------------------------------------------------------------------------
    | File Preview Endpoint
    |--------------------------------------------------------------------------
    |
    | URI used to serve temporary upload previews while a file is still in
    | the Ripple temp store. Emitted as <meta name="ripple-preview">.
    |
    */

    'preview_uri' => '/ripple/preview',

    /*
    |--------------------------------------------------------------------------
    | Component Namespace
    |--------------------------------------------------------------------------
    |
    | Default PHP namespace used when resolving short component names
    | (for example "counter" → App\Ripple\Counter).
    |
    */

    'component_namespace' => 'App\\Ripple\\',

    /*
    |--------------------------------------------------------------------------
    | Full-Page Layout
    |--------------------------------------------------------------------------
    |
    | Default Odo layout view for Route::ripple() pages when the component
    | has no #[Layout] attribute. The layout must #yield('content') and
    | include Ripple::scripts() in <head> for soft navigation.
    |
    */

    'layout' => env('RIPPLE_LAYOUT', 'ripple::layouts.app'),

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | signing_key: HMAC key for state signatures. Falls back to APP_KEY when
    |              null. Prefer a dedicated RIPPLE_SIGNING_KEY in production.
    |
    | verify_state:
    |   - "strict"            → verify signature on every update (default)
    |   - "property-updates"  → verify only on property sync (t=u); Locked
    |                           props are still ignored on unsigned actions
    |   - "none"              → skip verification (local/dev only)
    |
    */

    'security' => [
        'signing_key' => env('RIPPLE_SIGNING_KEY'),
        'verify_state' => env('RIPPLE_VERIFY_STATE', 'strict'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | debounce_default: Pause (ms) for ripple:bind.debounce when no
    |                   .debounce.N is set. Emitted as
    |                   <meta name="ripple-debounce"> for the client runtime.
    |
    */

    'performance' => [
        'debounce_default' => env('RIPPLE_DEBOUNCE_DEFAULT', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Navigation
    |--------------------------------------------------------------------------
    |
    | Progress bar shown during softNavigate (ripple:navigate links, soft
    | redirects, popstate). Bar mounts on <html> so body swaps keep it.
    | Emitted as <meta name="ripple-navigate-progress"> and
    | <meta name="ripple-navigate-progress-color"> for the client.
    |
    */

    'navigate' => [
        'show_progress_bar' => env('RIPPLE_NAVIGATE_PROGRESS', true),
        'progress_bar_color' => env('RIPPLE_NAVIGATE_PROGRESS_COLOR', '#2299dd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    |
    | disk: filesystem.disks.* name
    | temporary_path: relative path on that disk
    | max_size: max KB (also raise PHP upload_max_filesize)
    | cleanup_after_hours: for `php pool ripple:cleanup`
    | allowed_extensions: lowercase extensions accepted for upload, as an array
    |   or a comma-separated string. Server-executable types (php, phar,
    |   htaccess, …) are always rejected, even when listed here.
    |
    */

    'file_upload' => [
        'disk' => env('RIPPLE_FILE_DISK', 'local'),
        'temporary_path' => env('RIPPLE_FILE_TEMP_PATH', 'ripple/temp'),
        'max_size' => env('RIPPLE_FILE_MAX_SIZE', 12288),
        'cleanup_after_hours' => env('RIPPLE_FILE_CLEANUP_HOURS', 24),
        'allowed_extensions' => env(
            'RIPPLE_FILE_ALLOWED_EXTENSIONS',
            'jpg,jpeg,png,gif,webp,avif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,zip,mp3,wav,ogg,mp4,mov,webm',
        ),
    ],

];
