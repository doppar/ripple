# Query string

Keep filters and pagination in the URL so refresh and share work.

## Mark a property

```php
use Doppar\Ripple\Attributes\QueryString;

#[QueryString]
public string $q = '';

#[QueryString(as: 'p')]
public int $page = 1;

#[QueryString(history: true, keep: false)]
public string $status = '';
```

| Option | Default | Meaning |
|--------|---------|---------|
| `as` | property name | Query key in the URL |
| `history` | `true` | Write changes to the address bar. With `false` the property is still read from the URL on first render but is never written back |
| `keep` | `false` | Keep the key even when the value is empty |

## Behavior

- On first render, Ripple reads matching query params into the properties
- After updates, the client updates the URL with `history.replaceState` for properties that changed (properties with `history: false` are skipped)

Combine with `ripple:bind.live` for search boxes:

```odo
<input type="search" ripple:bind.live.debounce.300="q" placeholder="Search…">
```

## Tips

- Prefer short `as` aliases for common params (`p` for page)
- Reset `page` to `1` in `updated()` when filters change
- Use with [Pagination](pagination.md) for list UIs

Next: [Pagination](pagination.md)
