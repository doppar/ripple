<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Attributes\Computed;
use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Attributes\On;
use Doppar\Ripple\Attributes\QueryString;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Phaseolies\Http\Validation\Bind;
use Phaseolies\Support\Validation\Sanitizer;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

abstract class Component
{
    /**
     * The unique identifier of this component instance.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The component currently being rendered, if any.
     *
     * @var self|null
     */
    private static ?self $rendering = null;

    /**
     * Names of public properties synced with the client.
     *
     * @var list<string>
     */
    protected array $tracked = [];

    /**
     * Names of #[Locked] properties the client may not update.
     *
     * @var list<string>
     */
    protected array $locked = [];

    /**
     * Names of #[Computed] properties.
     *
     * @var list<string>
     */
    protected array $computed = [];

    /**
     * Names of properties excluded from the serialized state.
     *
     * @var list<string>
     */
    protected array $hidden = [];

    /**
     * The #[QueryString] property configuration keyed by property name.
     *
     * @var array<string, array{as: string, history: bool, keep: bool}>
     */
    protected array $urlProperties = [];

    /**
     * The #[On] listener method names keyed by event name.
     *
     * @var array<string, list<string>>
     */
    protected array $eventListeners = [];

    /**
     * The validation error messages keyed by field name.
     *
     * @var array<string, list<string>>
     */
    protected array $errorBag = [];

    /**
     * The component events dispatched during this request.
     *
     * @var list<array{name: string, params: list<mixed>, to: ?list<string>, self: bool}>
     */
    protected array $dispatchedEvents = [];

    /**
     * The browser events dispatched during this request.
     *
     * @var list<array{name: string, data: mixed}>
     */
    protected array $browserEvents = [];

    /**
     * Create a new component instance and register its properties and listeners.
     */
    public function __construct()
    {
        $this->id = $this->id !== '' ? $this->id : bin2hex(random_bytes(8));
        $this->initializeProperties();
        $this->registerEventListeners();
    }

    /**
     * Get the component instance identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the component instance identifier.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the component currently being rendered, if any.
     *
     * @return self|null
     */
    public static function current(): ?self
    {
        return self::$rendering;
    }

    /**
     * Run the callback with the given component marked as the one being rendered.
     *
     * @template T
     * @param self $component
     * @param callable(): T $callback
     * @return T
     */
    public static function whileRendering(self $component, callable $callback): mixed
    {
        $previous = self::$rendering;
        self::$rendering = $component;

        try {
            return $callback();
        } finally {
            self::$rendering = $previous;
        }
    }

    /**
     * Initialize the component when it is first mounted.
     *
     * @return void
     */
    public function mount(): void
    {
        //
    }

    /**
     * Hook that runs after the component state is hydrated.
     *
     * @return void
     */
    public function hydrateLifecycle(): void
    {
        //
    }

    /**
     * Hook that runs before a property is updated from the client.
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function updating(string $property, mixed $value): void
    {
        //
    }

    /**
     * Hook that runs after a property is updated from the client.
     *
     * @param string $property
     * @return void
     */
    public function updated(string $property): void
    {
        //
    }

    /**
     * Hook that runs before the component is serialized for the response.
     *
     * @return void
     */
    public function dehydrate(): void
    {
        //
    }

    /**
     * Render the component.
     *
     * Return inline HTML, an Odo view name, or a Response from view().
     *
     * @return string|\Phaseolies\Http\Response
     */
    abstract public function render(): mixed;

    /**
     * Get the data passed to the component's Odo view.
     *
     * Public properties (and error bag) available to Odo views.
     *
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $data = [];
        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getName() === 'id') {
                continue;
            }
            if (! $property->isInitialized($this)) {
                continue;
            }
            $data[$property->getName()] = $property->getValue($this);
        }

        $data['errors'] = $this->getErrorBag();

        return $data;
    }

    /**
     * Get the tracked public property values to serialize to the client.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = [];

        foreach ($this->tracked as $property) {
            if (in_array($property, $this->hidden, true)) {
                continue;
            }
            if (! property_exists($this, $property)) {
                continue;
            }
            $ref = new ReflectionProperty($this, $property);
            if ($ref->isInitialized($this)) {
                $state[$property] = $ref->getValue($this);
            }
        }

        return $state;
    }

    /**
     * Restore the component properties from a client state snapshot.
     *
     * @param array<string, mixed> $state
     * @param bool $allowLocked Apply #[Locked] props from $state (only after HMAC verify).
     * @return void
     */
    public function hydrate(array $state, bool $allowLocked = false): void
    {
        foreach ($state as $key => $value) {
            if (! property_exists($this, $key)) {
                continue;
            }
            if (in_array($key, $this->hidden, true) || in_array($key, $this->computed, true)) {
                continue;
            }
            if (! $allowLocked && in_array($key, $this->locked, true)) {
                continue;
            }
            $property = new ReflectionProperty($this, $key);
            if (! $property->isPublic() || $property->isStatic()) {
                continue;
            }
            $property->setValue($this, $this->castIncoming($property, $value));
        }

        $this->hydrateLifecycle();
    }

    /**
     * Update a property from the client and revalidate the affected fields.
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function updateProperty(string $property, mixed $value): void
    {
        $this->syncProperty($property, $value);

        if ($this->fieldsToRevalidate($property) !== []) {
            $this->validateOnly($property);
        }
    }

    /**
     * Apply a client value without running validateOnly (used when flushing a form before an action).
     *
     * @param string $property
     * @param mixed $value
     * @param bool $resetValidation Property updates keep existing errors. Actions clear the field.
     * @return void
     */
    public function syncProperty(string $property, mixed $value, bool $resetValidation = true): void
    {
        if (! property_exists($this, $property)) {
            throw new \InvalidArgumentException("Property [{$property}] does not exist on " . static::class);
        }
        if (in_array($property, $this->hidden, true) || in_array($property, $this->computed, true)) {
            throw new \InvalidArgumentException("Property [{$property}] cannot be updated from the client.");
        }
        if (in_array($property, $this->locked, true)) {
            throw new \InvalidArgumentException("Property [{$property}] is locked.");
        }

        $this->updating($property, $value);

        if ($resetValidation) {
            $this->resetValidation($property);
        }

        $ref = new ReflectionProperty($this, $property);
        $ref->setValue($this, $this->castIncoming($ref, $value));
        $this->updated($property);
    }

    /**
     * Determine whether a client value would leave the property as it is after coercion.
     *
     * Bound fields ride along with every action, so "5" for an int property that already
     * holds 5 is not a change and must not run the updating() and updated() hooks.
     *
     * @param string $property
     * @param mixed $value
     * @return bool
     */
    public function isUnchanged(string $property, mixed $value): bool
    {
        if (! property_exists($this, $property)) {
            return false;
        }

        $ref = new ReflectionProperty($this, $property);

        return $ref->isPublic()
            && $ref->isInitialized($this)
            && $ref->getValue($this) === $this->castIncoming($ref, $value);
    }

    /**
     * Coerce an incoming client value to the property's declared scalar type.
     *
     * Wire payloads arrive as JSON/strings; typed public props need coercion
     * (e.g. cleared number inputs send "").
     *
     * @param ReflectionProperty $ref
     * @param mixed $value
     * @return mixed
     */
    private function castIncoming(ReflectionProperty $ref, mixed $value): mixed
    {
        $type = $ref->getType();
        if (! $type instanceof ReflectionNamedType || ! $type->isBuiltin()) {
            return $value;
        }

        $name = $type->getName();
        $nullable = $type->allowsNull();

        if ($value === null || $value === '') {
            return match ($name) {
                'int' => $nullable ? null : 0,
                'float' => $nullable ? null : 0.0,
                'bool' => $nullable ? null : false,
                'string' => $nullable && $value === null ? null : '',
                'array' => $nullable && $value === null ? null : [],
                default => $value,
            };
        }

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => is_bool($value)
                ? $value
                : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Call an invokable action or event listener method on the component.
     *
     * @param string|null $action
     * @param list<mixed> $args
     * @param string|null $event Event being delivered to a #[On] listener, if any
     * @return mixed
     */
    public function callAction(?string $action, array $args = [], ?string $event = null): mixed
    {
        if ($action === null || $action === '') {
            return null;
        }

        if (! method_exists($this, $action)) {
            throw new \BadMethodCallException("Ripple action [{$action}] does not exist on " . static::class);
        }

        $method = new ReflectionMethod($this, $action);
        if (! $method->isPublic() || $method->isStatic()) {
            throw new \BadMethodCallException("Ripple action [{$action}] must be public on " . static::class);
        }

        // #[Invokable] opens a method to direct browser calls. A #[On] listener is reachable
        // only as a listener: the browser delivers events by naming the event it is delivering.
        if ($method->getAttributes(Invokable::class) === [] && ! $this->listensTo($event, $action)) {
            throw new \BadMethodCallException(
                "Ripple action [{$action}] is not invokable. Add #[Invokable] on " . static::class,
            );
        }

        return $this->{$action}(...$args);
    }

    /**
     * Determine if the method is registered as a listener for the event.
     *
     * @param string|null $event
     * @param string $method
     * @return bool
     */
    private function listensTo(?string $event, string $method): bool
    {
        return $event !== null && in_array($method, $this->eventListeners[$event] ?? [], true);
    }

    /**
     * Invoke every listener registered for the given event.
     *
     * @param string $eventName
     * @param list<mixed> $params
     * @return void
     */
    public function handleEvent(string $eventName, array $params = []): void
    {
        foreach ($this->eventListeners[$eventName] ?? [] as $methodName) {
            if (method_exists($this, $methodName)) {
                $this->{$methodName}(...$params);
            }
        }
    }

    /**
     * Get the registered event listeners keyed by event name.
     *
     * @return array<string, list<string>>
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * Get the #[QueryString] property configuration.
     *
     * @return array<string, array{as: string, history: bool, keep: bool}>
     */
    public function getUrlProperties(): array
    {
        return $this->urlProperties;
    }

    /**
     * Pull #[QueryString] props from the current request so a refresh keeps page/filters.
     *
     * @return void
     */
    public function hydrateFromQueryString(): void
    {
        if ($this->urlProperties === [] || ! function_exists('request')) {
            return;
        }

        try {
            $req = request();
        } catch (\Throwable) {
            return;
        }

        if (! is_object($req) || ! isset($req->query) || ! is_object($req->query)) {
            return;
        }

        foreach ($this->urlProperties as $property => $config) {
            $key = $config['as'];
            if (! method_exists($req->query, 'has') || ! $req->query->has($key)) {
                continue;
            }
            if (! property_exists($this, $property)) {
                continue;
            }

            $raw = $req->query->all()[$key] ?? null;
            $ref = new ReflectionProperty($this, $property);
            $ref->setValue($this, $this->castIncoming($ref, $raw));
        }
    }

    /**
     * Get the query string values to write to the browser URL.
     *
     * @return array<string, mixed|null>
     */
    public function getQueryString(): array
    {
        $query = [];
        foreach ($this->urlProperties as $property => $config) {
            // history: false → still read from the URL on first render, never written back
            if (! $config['history']) {
                continue;
            }

            $as = $config['as'];
            $value = property_exists($this, $property) ? $this->{$property} : null;

            if ($config['keep']) {
                $query[$as] = $value;
                continue;
            }

            // null ⇒ client deletes the param; also drop values that match the property default
            if ($value === null || $value === '') {
                $query[$as] = null;
                continue;
            }

            $ref = new ReflectionProperty($this, $property);
            if ($ref->hasDefaultValue() && $value === $ref->getDefaultValue()) {
                $query[$as] = null;
                continue;
            }

            $query[$as] = $value;
        }

        return $query;
    }

    /**
     * Get the validation rules for the component state.
     *
     * @return array<string, string|list<string>|Bind>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Get the custom validation messages keyed by "field.rule".
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Validate component state with Doppar's Sanitizer (full framework rule set).
     *
     * @param array<string, string|list<string>|Bind>|null $rules
     * @return array<string, mixed>
     */
    protected function validate(?array $rules = null): array
    {
        $rules ??= $this->rules();
        $state = $this->getState();
        $normalized = $this->normalizeValidationRules($rules);

        $errors = $this->runSanitizer($state, $normalized);

        if ($errors !== []) {
            $this->errorBag = $errors;

            throw new ValidationException($errors);
        }

        $this->resetValidation();

        return array_intersect_key($state, $normalized);
    }

    /**
     * Normalize array rule lists into pipe-delimited rule strings.
     *
     * @param array<string, string|list<string>|Bind> $rules
     * @return array<string, string|Bind>
     */
    private function normalizeValidationRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $rule) {
            if ($rule instanceof Bind) {
                $normalized[$field] = $rule;

                continue;
            }

            $normalized[$field] = is_array($rule) ? implode('|', $rule) : (string) $rule;
        }

        return $normalized;
    }

    /**
     * Run the rules against the state and collect error messages by field.
     *
     * @param array<string, mixed> $state
     * @param array<string, string|Bind> $rules
     * @return array<string, list<string>>
     */
    private function runSanitizer(array $state, array $rules): array
    {
        $custom = $this->messages();

        if ($custom === []) {
            $sanitizer = new Sanitizer($state, $rules);
            if ($sanitizer->validate()) {
                return [];
            }

            return $sanitizer->errors()['errors'] ?? [];
        }

        // Re-run per rule so messages() keys like "email.required" map correctly.
        $errors = [];

        foreach ($rules as $field => $ruleSpec) {
            if ($ruleSpec instanceof Bind) {
                $message = $ruleSpec->evaluate($field, $state);
                if ($message !== null) {
                    $errors[$field][] = $message;
                }

                continue;
            }

            foreach (explode('|', $ruleSpec) as $rule) {
                $rule = trim($rule);
                if ($rule === '') {
                    continue;
                }

                $probe = new Sanitizer($state, [$field => $rule]);
                if ($probe->validate()) {
                    continue;
                }

                $ruleName = explode(':', $rule, 2)[0];
                $fallback = $probe->errors()['errors'][$field][0] ?? 'Validation failed.';
                $errors[$field][] = $custom["{$field}.{$ruleName}"] ?? $fallback;
            }
        }

        return $errors;
    }

    /**
     * Revalidate the given field and any fields whose rules depend on it.
     *
     * @param string $field
     * @return void
     */
    protected function validateOnly(string $field): void
    {
        $rules = $this->rules();
        $fields = $this->fieldsToRevalidate($field);
        if ($fields === []) {
            return;
        }

        $kept = $this->errorBag;
        $subset = [];
        foreach ($fields as $name) {
            unset($kept[$name]);
            $subset[$name] = $rules[$name];
        }

        try {
            $this->errorBag = [];
            $this->validate($subset);
            $this->errorBag = $kept;
        } catch (ValidationException $e) {
            $this->errorBag = array_merge($kept, $e->errors());
        }
    }

    /**
     * Get the fields to revalidate when the given field changes.
     *
     * The changed field, plus any other field whose rule reads it.
     *
     * @param string $field
     * @return list<string>
     */
    private function fieldsToRevalidate(string $field): array
    {
        $rules = $this->rules();
        $fields = [];

        if (array_key_exists($field, $rules)) {
            $fields[] = $field;
        }

        foreach ($rules as $name => $rule) {
            if ($name === $field || $rule instanceof Bind) {
                continue;
            }

            $spec = is_array($rule) ? implode('|', $rule) : (string) $rule;
            if ($this->ruleReadsField($spec, (string) $name, $field)) {
                $fields[] = (string) $name;
            }
        }

        return $fields;
    }

    /**
     * Determine if a rule string on one field references the changed field.
     *
     * @param string $spec
     * @param string $owner
     * @param string $changed
     * @return bool
     */
    private function ruleReadsField(string $spec, string $owner, string $changed): bool
    {
        foreach (explode('|', $spec) as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            $parts = explode(':', $rule, 2);
            $name = $parts[0];
            $param = $parts[1] ?? null;

            if ($name === 'confirmed' && $changed === $owner . '_confirmation') {
                return true;
            }

            if (($name === 'different' || $name === 'same_as') && $param === $changed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear validation errors for one field, or all fields when none is given.
     *
     * @param string|null $field
     * @return void
     */
    protected function resetValidation(?string $field = null): void
    {
        if ($field === null) {
            $this->errorBag = [];

            return;
        }
        unset($this->errorBag[$field]);
    }

    /**
     * Add a validation error message for the given field.
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    protected function addError(string $field, string $message): void
    {
        $this->errorBag[$field][] = $message;
    }

    /**
     * Get the validation errors keyed by field name.
     *
     * @return array<string, list<string>>
     */
    public function getErrorBag(): array
    {
        return $this->errorBag;
    }

    /**
     * Replace the validation errors.
     *
     * @param array<string, list<string>> $errors
     * @return void
     */
    public function setErrorBag(array $errors): void
    {
        $this->errorBag = $errors;
    }

    /**
     * Redirect the browser to the given URL.
     *
     * @param string $url
     * @param bool $spa
     * @return never
     */
    protected function redirect(string $url, bool $spa = true): never
    {
        throw new RedirectException(['u' => $url, 'spa' => $spa]);
    }

    /**
     * Dispatch an event to every listening component.
     *
     * @param string $event
     * @param mixed ...$params
     * @return self
     */
    protected function dispatch(string $event, mixed ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => array_values($params),
            'to' => null,
            'self' => false,
        ];

        return $this;
    }

    /**
     * Dispatch an event to the given components only.
     *
     * @param string|list<string> $components
     * @param string $event
     * @param mixed ...$params
     * @return self
     */
    protected function dispatchTo(string|array $components, string $event, mixed ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => array_values($params),
            'to' => is_array($components) ? $components : [$components],
            'self' => false,
        ];

        return $this;
    }

    /**
     * Dispatch an event to this component only.
     *
     * @param string $event
     * @param mixed ...$params
     * @return self
     */
    protected function dispatchSelf(string $event, mixed ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => array_values($params),
            'to' => [$this->id],
            'self' => true,
        ];

        return $this;
    }

    /**
     * Dispatch a DOM event in the browser.
     *
     * @param string $event
     * @param mixed $data
     * @return self
     */
    protected function dispatchBrowserEvent(string $event, mixed $data = null): self
    {
        $this->browserEvents[] = ['name' => $event, 'data' => $data];

        return $this;
    }

    /**
     * Get the component events dispatched during this request.
     *
     * @return list<array{name: string, params: list<mixed>, to: ?list<string>, self: bool}>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Get the browser events dispatched during this request.
     *
     * @return list<array{name: string, data: mixed}>
     */
    public function getBrowserEvents(): array
    {
        return $this->browserEvents;
    }

    /**
     * Clear the dispatched component and browser events.
     *
     * @return void
     */
    public function clearDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
        $this->browserEvents = [];
    }

    /**
     * Run the dehydrate hook before the component is sent in the response.
     *
     * @return void
     */
    public function dehydrateForResponse(): void
    {
        $this->dehydrate();
    }

    /**
     * Classify public properties as tracked, locked, computed or query-string bound.
     *
     * @return void
     */
    private function initializeProperties(): void
    {
        $ref = new ReflectionClass($this);
        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getName() === 'id') {
                continue;
            }
            $name = $property->getName();

            if ($property->getAttributes(Computed::class) !== []) {
                $this->computed[] = $name;
                $this->hidden[] = $name;
                continue;
            }

            if ($property->getAttributes(Locked::class) !== []) {
                $this->locked[] = $name;
            }

            $this->tracked[] = $name;

            $qs = $property->getAttributes(QueryString::class);
            if ($qs !== []) {
                /** @var QueryString $attr */
                $attr = $qs[0]->newInstance();
                $this->urlProperties[$name] = [
                    'as' => $attr->as ?? $name,
                    'history' => $attr->history,
                    'keep' => $attr->keep,
                ];
            }
        }
    }

    /**
     * Register public methods carrying #[On] attributes as event listeners.
     *
     * @return void
     */
    private function registerEventListeners(): void
    {
        $ref = new ReflectionClass($this);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || str_starts_with($method->getName(), '__')) {
                continue;
            }
            foreach ($method->getAttributes(On::class) as $attribute) {
                /** @var On $on */
                $on = $attribute->newInstance();
                $this->eventListeners[$on->event][] = $method->getName();
            }
        }
    }
}
