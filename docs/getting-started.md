# Getting started

This walkthrough builds a small counter and mounts it on a page.

## 1. Generate a component

```bash
php pool make:ripple Counter
```

Creates:

- `src/Ripple/Counter.php`
- `templates/views/ripple/counter.odo.php`

Use `--inline` to keep HTML inside `render()` instead of an Odo view.

## 2. Define state and actions

Public properties are component state. Methods the browser may call need `#[Invokable]`.

```php
<?php

namespace App\Ripple;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

class Counter extends Component
{
    public int $count = 0;

    #[Invokable]
    public function increment(): void
    {
        $this->count++;
    }

    #[Invokable]
    public function decrement(): void
    {
        $this->count--;
    }

    public function render(): mixed
    {
        return view('ripple.counter');
    }
}
```

`render()` may return:

- `view('ripple.counter')`
- a view name string: `'ripple.counter'`
- inline HTML: `'<div>...</div>'`

## 3. Write the view

Public properties are available as variables. Use `ripple:call` to invoke actions.

```odo
<div>
    <p>Count: [[ $count ]]</p>
    <button type="button" ripple:call="decrement">−</button>
    <button type="button" ripple:call="increment">+</button>
</div>
```

Do not wrap the root yourself with `data-ripple`. `mount()` adds that.

## 4. Mount on a page

**Island** (embed in an existing view):

```odo
[[! \Doppar\Ripple\Facade\Ripple::mount(\App\Ripple\Counter::class) !]]
```

Pass constructor / mount params as a second argument when needed:

```php
Ripple::mount(\App\Ripple\Counter::class, ['count' => 10]);
```

**Full page** (component owns the route):

```php
Route::ripple('/counter', \App\Ripple\Counter::class)->name('counter');
```

Optional `#[Layout]` / `#[Title]` on the component. See [Navigation](navigation.md#full-page-routes-routeripple).

Ensure [scripts are in the layout](installation.md#load-the-client).

## 5. What happens on click

1. The client posts the action and signed state to `/ripple/update`
2. Ripple hydrates the component, runs `increment`, re-renders
3. A DOM diff returns only the patches needed
4. The client applies those patches

You work in PHP and Odo. The wire format stays internal.

Next: [Components](components.md) · [Directives](directives.md)
