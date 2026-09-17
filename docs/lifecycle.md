# Lifecycle

Hooks run on the server as the component mounts, hydrates, and updates.

## Order of interest

| Hook | When |
|------|------|
| `mount()` | First HTML render (not on later AJAX updates) |
| `hydrateLifecycle()` | After state is applied on an update request |
| `updating($property, $value)` | Before a property sync is applied |
| `updated($property)` | After a property sync is applied |
| `dehydrate()` | Before the response is built |

Actions (`#[Invokable]` methods) run after hydrate on action requests.

## Examples

### Seed data once

```php
public function mount(): void
{
    $this->orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(3)));
}
```

### React to binds

```php
public function updated(string $property): void
{
    if ($property === 'query') {
        $this->page = 1;
    }
}
```

### Recompute derived fields after hydrate

```php
public function hydrateLifecycle(): void
{
    $this->refreshSummary();
}
```

Use this when `#[Computed]` values must stay correct after every round trip.

### Guard before assign

```php
public function updating(string $property, mixed $value): void
{
    if ($property === 'role' && ! in_array($value, ['user', 'admin'], true)) {
        // throw or ignore via your own logic
    }
}
```

Locked properties already reject client property updates; `updating` is for custom rules.

## Query string on first paint

On mount / first render, Ripple can hydrate `#[QueryString]` properties from the current request URL so refresh keeps filters. See [Query string](query-string.md).

Next: [Validation](validation.md) · [Events](events.md)
