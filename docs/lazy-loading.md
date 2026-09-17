# Lazy loading

Defer heavy components until the client asks for them. Useful for dashboards and below-the-fold widgets.

## Mark the class

```php
use Doppar\Ripple\Attributes\Lazy;

#[Lazy(placeholder: '<div class="p-4 text-slate-400">Loading…</div>')]
class SalesDashboard extends Component
{
    public function mount(): void
    {
        // expensive work runs only when the lazy endpoint hydrates
    }

    public function render(): mixed
    {
        return view('ripple.sales-dashboard');
    }
}
```

`placeholder` is optional HTML shown until the full component loads. If omitted, a simple default placeholder is used.

## Mount as usual

```odo
[[! \Doppar\Ripple\Facade\Ripple::mount(\App\Ripple\SalesDashboard::class) !]]
```

The first response is a lightweight root with `data-ripple-lazy`. The client requests `/ripple/lazy`, then injects the full HTML and continues with normal updates.

## Tips

- Keep placeholders small and layout-stable to avoid CLS
- Do expensive queries in `mount()`, not in property defaults
- Nested lazy components each hydrate on their own

Next: [File uploads](file-uploads.md)
