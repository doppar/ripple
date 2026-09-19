# Testing

Test Ripple components with PHPUnit through `Ripple::test()`. It mounts the component, drives real update payloads (signed state, actions, property updates), and gives you a fluent assertion API.

Run these tests under your **app** PHPUnit bootstrap so the facade and view engine are available.

## Basic usage

```php
use Doppar\Ripple\Facade\Ripple;
use App\Ripple\Counter;
use Tests\TestCase;

class CounterTest extends TestCase
{
    public function test_increments(): void
    {
        Ripple::test(Counter::class)
            ->assertSet('count', 0)
            ->call('increment')
            ->assertSet('count', 1)
            ->assertSee('Count: 1');
    }
}
```

Pass mount params as the second argument:

```php
Ripple::test(Counter::class, ['count' => 5])
    ->assertSet('count', 5);
```

## Actions and properties

| Method | What it does |
|--------|----------------|
| `call('action', ...$args)` | Runs an `#[Invokable]` action |
| `set('prop', $value)` | Property update (`ripple:bind`) |
| `set(['a' => 1, 'b' => 2])` | Multiple property updates |
| `fill([...])` | Alias for array `set` |
| `toggle('flag')` | Flip a boolean property |
| `refresh()` | Re-render HTML from current state |

## Assertions

| Method | Purpose |
|--------|---------|
| `assertSet` / `assertNotSet` / `assertSetStrict` | Public property values |
| `assertSee` / `assertDontSee` / `assertSeeHtml` | Rendered HTML |
| `assertViewHas` | Key (and optional value) in `viewData()` |
| `assertOk` / `assertNotOk` | Soft validation success flag |
| `assertHasErrors` / `assertHasNoErrors` | Validation error bag |
| `assertRedirect` | Redirect from `$this->redirect()` |
| `assertDispatched` / `assertNotDispatched` | Component events |
| `assertBrowserEvent` | Browser events |

### Validation

```php
Ripple::test(ContactForm::class)
    ->set('email', 'bad')
    ->call('save')
    ->assertNotOk()
    ->assertHasErrors('email');
```

### Redirects

```php
Ripple::test(SpaNav::class)
    ->call('softToCounter')
    ->assertRedirect('/demos/counter');
```

### Events

```php
Ripple::test(EventPublisher::class)
    ->call('shout')
    ->assertDispatched('announce');
```

## Accessors

| Method | Returns |
|--------|---------|
| `get('prop')` | Property value (dot paths supported) |
| `html()` | Latest rendered HTML (without the root wrapper) |
| `instance()` | Hydrated component from current state |
| `viewData()` / `viewData('key')` | View data array or one key |
| `errors()` | Current error bag |
| `effects()` | Last update response payload |
| `id()` | Component id |

## Notes

- Updates go through `ComponentManager::update` with HMAC signatures. Locked properties and invokable gates behave like production.
- Lazy components (`#[Lazy]`) are loaded via `loadLazy()` on first `Ripple::test()` so the full HTML and state are available before assertions.
- File upload simulation is not included in this helper yet.
