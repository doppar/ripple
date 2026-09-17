# Pagination

Use the built-in trait for page state and controls.

## Trait

```php
use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;
use Doppar\Ripple\Traits\HasPagination;

class ProductSearch extends Component
{
    use HasPagination;

    public string $q = '';

    // $page (query-string) and $perPage come from the trait

    #[Invokable]
    public function search(): void
    {
        $this->page = 1;
    }

    public function render(): mixed
    {
        return view('ripple.product-search', [
            'results' => $this->results(),
        ]);
    }
}
```

`HasPagination` provides:

| Member | Role |
|--------|------|
| `$page` | Current page (`#[QueryString]`) |
| `$perPage` | Page size (default 15) |
| `gotoPage(int $page)` | Jump |
| `nextPage()` | Next |
| `previousPage()` | Previous (clamped at 1) |

## View controls

```odo
<button type="button" ripple:call="previousPage">Prev</button>
<span>Page [[ $page ]]</span>
<button type="button" ripple:call="nextPage">Next</button>
<button type="button" ripple:call="gotoPage(3)">Go to 3</button>
```

## Query your data

Use `$this->page` and `$this->perPage` in your query layer (Eloquent / Query Builder / raw SQL). Ripple does not force a specific paginator object; keep slicing in the component or a repository.

```php
private function results(): array
{
    $offset = max(0, ($this->page - 1) * $this->perPage);
    // ... limit $this->perPage offset $offset
}
```

When filters change, set `$this->page = 1` so users do not land on an empty page.

Next: [Lazy loading](lazy-loading.md)
