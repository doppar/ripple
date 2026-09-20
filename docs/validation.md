# Validation

Use Doppar validation rules inside components. Failed validation returns errors in the same update response (HTTP 422); the component re-renders with `$errors` available in the view.

## Define rules

```php
protected function rules(): array
{
    return [
        'email' => 'required|email',
        'name' => 'required|min:2',
    ];
}

protected function messages(): array
{
    return [
        'email.required' => 'Please enter your email.',
    ];
}
```

## Validate in an action

```php
#[Invokable]
public function save(): void
{
    $this->validate();
    // persist...
}
```

Helpers:

| Method | Purpose |
|--------|---------|
| `validate(?array $rules = null)` | Validate all (or override) rules; throws on failure |
| `validateOnly(string $field)` | Validate one field |
| `resetValidation(?string $field = null)` | Clear errors |
| `addError(string $field, string $message)` | Manual error |

## Show errors in Odo

`$errors` is a bag of `field => list of messages`:

```odo
<input type="email" ripple:bind="email">
#if (!empty($errors['email'][0]))
    <p class="text-red-600">[[ $errors['email'][0] ]]</p>
#endif
```

## Step forms

Swap rules by step:

```php
protected function rules(): array
{
    return match ($this->step) {
        1 => [
            'name' => 'required|min:2',
            'email' => 'required|email',
        ],
        2 => [
            'plan' => 'required',
        ],
        default => [],
    };
}
```

Next: [Events](events.md)
