# Events

Ripple supports server-side component events and browser `CustomEvent`s.

## Listen with `#[On]`

```php
use Doppar\Ripple\Attributes\On;

#[On('announce')]
public function onAnnounce(string $message = '', int $n = 0): void
{
    $this->last = $message;
}
```

Listeners are public methods. They run when a matching event is dispatched to that component (or globally, depending on how it was sent).

Mark the listener `#[Invokable]` only if the browser should also call it directly.

## Dispatch from a component

```php
// Any mounted component listening for "announce"
$this->dispatch('announce', $this->message, $this->sent);

// Only specific component class names / aliases
$this->dispatchTo('EventListenerBoard', 'announce', $this->message);
$this->dispatchTo([\App\Ripple\Board::class], 'announce', $this->message);

// Only this instance
$this->dispatchSelf('local-ping');
```

Dispatches are delivered as part of the update response so sibling components on the same page can react in the same round trip.

## Browser events

```php
$this->dispatchBrowserEvent('ripple:toast', [
    'text' => $this->message,
    'n' => $this->sent,
]);
```

In the page (layout or page script):

```html
<script>
window.addEventListener('ripple:toast', (e) => {
    console.log(e.detail.text, e.detail.n);
});
</script>
```

Name browser events with a clear prefix (for example `ripple:`) so they do not collide with native events.

## Typical pattern

Publisher component:

```php
#[Invokable]
public function shout(): void
{
    $this->dispatch('announce', $this->message);
    $this->dispatchBrowserEvent('ripple:toast', ['text' => $this->message]);
}
```

Listener component on the same page with `#[On('announce')]`.

Next: [Query string](query-string.md)
