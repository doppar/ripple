# File uploads

File inputs use temporary storage first. You move files to a permanent path when the user saves.

## Bind a file input

```odo
<input type="file" accept="image/*" ripple:bind="avatar">
<input type="file" multiple ripple:bind="docs">
```

- Single file → property becomes a temporary id string
- `multiple` → property becomes a list of temporary ids

Preview while still temporary:

```odo
#if ($avatar !== '')
    <img src="/ripple/preview?id=[[ $avatar ]]" alt="Preview">
#endif
```

## Handle updates

```php
use Doppar\Ripple\FileUpload\FileUploadService;

public string $avatar = '';

public function updated(string $property): void
{
    if ($property === 'avatar' && $this->avatar !== '') {
        $name = $this->uploads()->getTemporaryFileOriginalName($this->avatar);
        // ...
    }
}

private function uploads(): FileUploadService
{
    return app(FileUploadService::class);
}
```

## Persist

```php
#[Invokable]
public function save(): void
{
    if ($this->avatar !== '') {
        $path = $this->uploads()->moveToPermanent(
            $this->avatar,
            'uploads/avatars/' . $this->avatar,
            useOriginalName: true,
        );
        $this->avatar = '';
    }
}
```

Useful `FileUploadService` methods:

| Method | Purpose |
|--------|---------|
| `getTemporaryFileOriginalName($id)` | Original client filename |
| `getTemporaryPath($id)` | Relative path on the disk |
| `moveToPermanent($id, $destination, ?$disk, $useOriginalName)` | Move out of temp |
| `deleteTemporary($id)` | Discard a temp file |

## Config

In `config/ripple.php` under `file_upload`:

- `disk` — filesystem disk name
- `temporary_path` — folder for temp files
- `max_size` — max size in KB (also raise PHP `upload_max_filesize` / `post_max_size`)
- `cleanup_after_hours` — age for the cleanup command

## Cleanup

```bash
php pool ripple:cleanup
```

Schedule it in production so abandoned temp files do not accumulate.

## Client errors

Listen for upload failures:

```html
<script>
window.addEventListener('ripple:upload-error', (e) => {
    alert(e.detail.message);
});
</script>
```

Next: [Navigation](navigation.md)
