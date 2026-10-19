# Directives

Ripple HTML attributes live on elements inside a mounted component. Names use a `ripple:` prefix.

## Actions: `ripple:call`

Call an `#[Invokable]` method.

```odo
<button type="button" ripple:call="save">Save</button>
<button type="button" ripple:call="remove(3)">Remove</button>
<button type="button" ripple:call="rename('draft')">Rename</button>
```

Arguments are parsed from the attribute string and sent to the server.

### Forms: `ripple:submit`

Call an `#[Invokable]` method when the form is submitted. The browser does not navigate.

```odo
<form ripple:submit="save">
    <input ripple:bind="email">
    <button type="submit">Save</button>
</form>
```

A submit button can name its own method. That wins over `ripple:submit` on the form:

```odo
<form ripple:submit="save">
    <button type="submit">Save</button>
    <button type="submit" ripple:submit="saveDraft">Save draft</button>
</form>
```

## Binding: `ripple:bind`

Sync an input with a public property. On mount (and after each update), Ripple fills the control from component state — you do not need `value="[[ $name ]]"` in the view.

| Form | When it syncs |
|------|----------------|
| `ripple:bind="name"` | On the next Ripple request. A call, a `.live` / `.lazy` / `.debounce` update, or a poll sends the current value along. |
| `ripple:bind.live="q"` | On `input` (each keystroke) |
| `ripple:bind.lazy="bio"` | On `change` (blur for text) |
| `ripple:bind.debounce="q"` | On `input`, after the default pause |
| `ripple:bind.debounce.300="q"` | On `input`, after 300ms |

`.live.debounce.300` is the same pause as `.debounce.300`. The default pause is `performance.debounce_default` (usually 150ms).

```odo
<input type="text" ripple:bind="name">
<input type="search" ripple:bind.live="q">
<input type="text" ripple:bind.lazy="bio">
<input type="search" ripple:bind.debounce.300="q">
<input type="checkbox" ripple:bind="newsletter">
<select ripple:bind="theme">...</select>
<input type="file" ripple:bind="avatar">
<input type="file" multiple ripple:bind="docs">
```

File inputs upload to the temp endpoint, then set the property to a temp id (or list of ids). See [File uploads](file-uploads.md).

## Loading UI

```odo
<span ripple:loading style="display:none">Saving…</span>
<button type="button" ripple:call="save" ripple:loading.target>Save</button>
```

- `ripple:loading` — shown while a request for that component is in flight
- `ripple:loading.target` — marked busy / disabled during the request

## Confirm

```odo
<button type="button"
        ripple:call="delete"
        ripple:confirm="Delete this item?">
    Delete
</button>
```

Uses the browser `confirm()` dialog before sending the request.

## SPA links: `ripple:navigate`

Intercept same-origin link clicks and soft-navigate (fetch HTML, swap `body`, `pushState`) instead of a full reload:

```odo
<a href="/ripple/counter" ripple:navigate>Counter</a>
```

Skipped automatically for modified clicks, new tabs (`target`), downloads, cross-origin URLs, and hash-only same-page links. Scripts in the layout `<head>` (including `Ripple::scripts()`) stay loaded across soft navigations. A top progress bar shows when the fetch takes longer than ~150ms; see [Navigation](navigation.md#progress-bar).

## Polling

```odo
<div ripple:poll="2s" ripple:poll.call="refresh">
    Last refresh: [[ $refreshedAt ]]
</div>
```

- `ripple:poll` — interval (`2s`, `500ms`, or a number of ms)
- `ripple:poll.call` — method to invoke (default `refresh`)

The method must be `#[Invokable]`.

Polling stops when the element leaves the DOM, so you can turn it off with a condition:

```odo
#if ($running)
    <div ripple:poll="2s"></div>
#endif
```

Rendering the element again starts polling again. A poll tick is skipped while another request for the same component is still in flight.

## Request order

Updates for one component run one at a time, in the order they were triggered. Each update starts from the state the previous response returned, so quick successive edits (autofill, tabbing through fields) never overwrite each other.

## Tips

- Keep `ripple:call`, `ripple:submit`, and `ripple:bind` inside the mounted root. Outside the root they do nothing.
- Use plain `ripple:bind` when the server only needs the value on the next request. Add `.live` or `.debounce` where it should see each edit.
- Put layout-level scripts in `<head>` so loading and calls work after soft navigation.

Next: [Lifecycle](lifecycle.md) · [Validation](validation.md) · [Navigation](navigation.md)
