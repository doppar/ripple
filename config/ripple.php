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
    | the Ripple temp store.
    |
    */

    'preview_uri' => '/ripple/preview',

    /*
    |--------------------------------------------------------------------------
    | Component Namespace
    |--------------------------------------------------------------------------
    |
    | Default PHP namespace used when resolving short component names
    | (for example "Counter" → App\Ripple\Counter).
    |
    */

    'component_namespace' => 'App\\Ripple\\',

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | signing_key: HMAC key for state signatures. Falls back to APP_KEY when
    |              null. Prefer a dedicated RIPPLE_SIGNING_KEY in production.
    |
    | verify_state:
    |   - "strict"            → verify signature on every update
    |   - "property-updates"  → verify only on property sync (t=u)
    |   - "none"              → skip verification (local/dev only)
    |
    */

    'security' => [
        'signing_key' => env('RIPPLE_SIGNING_KEY'),
        'verify_state' => env('RIPPLE_VERIFY_STATE', 'property-updates'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | debounce_default: Default debounce window (ms) for ripple:bind.live when
    |                   no explicit .debounce.N modifier is set.
    |
    | minify_patches: When true, prefer compact delta payloads in responses.
    |
    */

    'performance' => [
        'debounce_default' => 150,
        'minify_patches' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    |
    | Temporary upload storage used before a component moves files to a
    | permanent location.
    |
    | disk: Filesystem disk name (see config/filesystem.php).
    | temporary_path: Path under the disk root for pending uploads.
    | max_size: Maximum upload size in kilobytes.
    | cleanup_after_hours: Age after which orphaned temp files are removed
    |                      by the ripple:cleanup-temp command.
    |
    */

    'file_upload' => [
        'disk' => env('RIPPLE_FILE_DISK', 'local'),
        'temporary_path' => env('RIPPLE_FILE_TEMP_PATH', 'ripple/temp'),
        'max_size' => env('RIPPLE_FILE_MAX_SIZE', 12288),
        'cleanup_after_hours' => env('RIPPLE_FILE_CLEANUP_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Extra Ripple diagnostics. Application-level error pages still follow
    | APP_DEBUG for failed updates that bubble to Doppar's error handler.
    |
    */

    'debug' => env('RIPPLE_DEBUG', false),

];
