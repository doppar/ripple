# Components

A Ripple component is a PHP class that extends `Doppar\Ripple\Component`. It holds state, handles actions, and renders HTML.

## Class layout

```php
namespace App\Ripple;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

class TodoList extends Component
{
    public string $draft = '';

    /** @var list<string> */
    public array $items = [];

    #[Invokable]
    public function add(): void
    {
        // ...
    }

    public function render(): mixed
    {
        return view('ripple.todo-list');
    }
}
```

Default namespace for short names is `App\Ripple\` (configurable).

## Public properties = state

Every non-static public property (except `id`) is tracked unless marked `#[Computed]`.

- Sent to the browser as signed state
- Available in the Odo view by the same name
- Updated from the client via `ripple:bind` or action methods

Use typed properties and sensible defaults.

## Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Invokable]` | method | Allow the browser to call this method |
| `#[Locked]` | property | Client cannot change this property via property sync |
| `#[Computed]` | property | Available in the view; never sent on the wire |
| `#[QueryString]` | property | Sync with the URL query string |
| `#[On('event')]` | method | Listen for a component event |
| `#[Lazy]` | class | Defer full mount until visible / requested |

### Locked

```php
#[Locked]
public string $orderId = '';
```

Useful for server-assigned ids that must not be forged from the client.

### Computed

```php
#[Computed]
public string $summary = '';
```

Set these in `mount()`, `hydrateLifecycle()`, or `updated()`. They appear in the view but are not part of signed state.

## Markup rules

Ripple diffs the HTML your component renders and patches the live DOM, so the markup has to parse the same way on the server and in the browser:

- Write valid HTML. Browsers rewrite invalid nesting (for example a `<p>` inside a `<p>`), which puts patch paths out of sync.
- Tables are fine as written: Ripple mirrors the `<tbody>` browsers insert around bare `<tr>` rows.
- Conditional blocks (`#if`) may appear and disappear anywhere. Nodes that exist before and after are patched in place, so inputs keep their focus and typed text.
- For lists that grow or shrink in the middle, give each item a stable `data-ripple-key` so the right item is patched, inserted or removed:

```odo
#foreach ($items as $item)
    <li data-ripple-key="[[ $item->id ]]">[[ $item->name ]]</li>
#endforeach
```

## Creating components

```bash
php pool make:ripple ContactForm
php pool make:ripple Admin/UserTable
php pool make:ripple Badge --inline
```

Nested names map to folders under `src/Ripple/` and `templates/views/ripple/`.

## Mounting

```php
echo ripple('counter');
echo ripple('counter', ['count' => 5]);
echo ripple(\App\Ripple\Counter::class);
```

In Odo:

```odo
[[! ripple('counter') !]]
```

Short names resolve under `component_namespace` (`App\Ripple` by default). `counter`, `Counter`, and `sales-dashboard` map to the matching class. A fully qualified class name still works.

## View data

`viewData()` exposes all public properties plus `$errors` (validation bag). Extra keys can be passed through `view()`:

```php
return view('ripple.preference-panel', [
    'themes' => ['tide' => 'Tide', 'ink' => 'Ink'],
]);
```

## Aliases

Register a name that does not match the class:

```php
use Doppar\Ripple\Facade\Ripple;

Ripple::register('cart', \App\Ripple\Checkout::class);
```

Next: [Directives](directives.md) · [Lifecycle](lifecycle.md)
