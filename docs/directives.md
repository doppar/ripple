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

### Event: `ripple:on`

Default trigger is `click`. For forms, use `submit`:

```odo
<form ripple:call="save" ripple:on="submit">
    <input ripple:bind="email">
    <button type="submit">Save</button>
</form>
```

## Binding: `ripple:bind`

Sync an input with a public property.

| Form | When it syncs |
|------|----------------|
| `ripple:bind="name"` | On `change` (blur for text; immediate for checkbox, radio, select) |
| `ripple:bind.live="q"` | On `input` (each keystroke) |
| `ripple:bind.live.debounce.300="q"` | Live, debounced by 300ms |
| `ripple:bind.lazy="bio"` | On `change` only (explicit) |

Default debounce for `.live` without `.debounce.N` comes from config (`performance.debounce_default`, usually 150ms).

```odo
<input type="text" ripple:bind.live.debounce.400="query">
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

Skipped automatically for modified clicks, new tabs (`target`), downloads, cross-origin URLs, and hash-only same-page links. Scripts in the layout `<head>` (including `Ripple::scripts()`) stay loaded across soft navigations.

## Polling

```odo
<div ripple:poll="2s" ripple:poll.call="refresh">
    Last refresh: [[ $refreshedAt ]]
</div>
```

- `ripple:poll` — interval (`2s`, `500ms`, or a number of ms)
- `ripple:poll.call` — method to invoke (default `refresh`)

The method must be `#[Invokable]`.

## Tips

- Keep `ripple:call` / `ripple:bind` inside the mounted root. Outside the root they do nothing.
- Prefer `.live` only where you need instant search-style feedback; default bind is cheaper.
- Put layout-level scripts in `<head>` so loading and calls work after soft navigation.

Next: [Lifecycle](lifecycle.md) · [Validation](validation.md) · [Navigation](navigation.md)
