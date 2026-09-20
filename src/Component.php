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
    protected string $id = '';

    private static ?self $rendering = null;

    /** @var list<string> */
    protected array $tracked = [];

    /** @var list<string> */
    protected array $locked = [];

    /** @var list<string> */
    protected array $computed = [];

    /** @var list<string> */
    protected array $hidden = [];

    /** @var array<string, array{as: string, history: bool, keep: bool}> */
    protected array $urlProperties = [];

    /** @var array<string, list<string>> */
    protected array $eventListeners = [];

    /** @var array<string, list<string>> */
    protected array $errorBag = [];

    /** @var list<array{name: string, params: list<mixed>, to: ?list<string>, self: bool}> */
    protected array $dispatchedEvents = [];

    /** @var list<array{name: string, data: mixed}> */
    protected array $browserEvents = [];

    public function __construct()
    {
        $this->id = $this->id !== '' ? $this->id : bin2hex(random_bytes(8));
        $this->initializeProperties();
        $this->registerEventListeners();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public static function current(): ?self
    {
        return self::$rendering;
    }

    /**
     * @template T
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

    public function mount(): void
    {
        //
    }

    public function hydrateLifecycle(): void
    {
        //
    }

    public function updating(string $property, mixed $value): void
    {
        //
    }

    public function updated(string $property): void
    {
        //
    }

    public function dehydrate(): void
    {
        //
    }

    /**
     * Return inline HTML, an Odo view name, or a Response from view().
     *
     * @return string|\Phaseolies\Http\Response
     */
    abstract public function render(): mixed;

    /**
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
     * @param array<string, mixed> $state
     * @param bool $allowLocked Apply #[Locked] props from $state (only after HMAC verify).
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

    public function updateProperty(string $property, mixed $value): void
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

        $this->resetValidation($property);

        $ref = new ReflectionProperty($this, $property);
        $ref->setValue($this, $this->castIncoming($ref, $value));
        $this->updated($property);

        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }
    }

    /**
     * Wire payloads arrive as JSON/strings; typed public props need coercion
     * (e.g. cleared number inputs send "").
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
     * @param string|null $action
     * @param list<mixed> $args
     * @param string|null $event Event being delivered to a #[On] listener, if any
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

    private function listensTo(?string $event, string $method): bool
    {
        return $event !== null && in_array($method, $this->eventListeners[$event] ?? [], true);
    }

    /**
     * @param list<mixed> $params
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
     * @return array<string, list<string>>
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * @return array<string, array{as: string, history: bool, keep: bool}>
     */
    public function getUrlProperties(): array
    {
        return $this->urlProperties;
    }

    /**
     * Pull #[QueryString] props from the current request so a refresh keeps page/filters.
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
     * @return array<string, string|list<string>|Bind>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
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

    protected function validateOnly(string $field): void
    {
        $rules = $this->rules();
        if (! isset($rules[$field])) {
            return;
        }

        $kept = $this->errorBag;
        unset($kept[$field]);

        try {
            $this->errorBag = [];
            $this->validate([$field => $rules[$field]]);
            $this->errorBag = $kept;
        } catch (ValidationException $e) {
            $this->errorBag = array_merge($kept, $e->errors());
        }
    }

    protected function resetValidation(?string $field = null): void
    {
        if ($field === null) {
            $this->errorBag = [];

            return;
        }
        unset($this->errorBag[$field]);
    }

    protected function addError(string $field, string $message): void
    {
        $this->errorBag[$field][] = $message;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrorBag(): array
    {
        return $this->errorBag;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public function setErrorBag(array $errors): void
    {
        $this->errorBag = $errors;
    }

    protected function redirect(string $url, bool $spa = true): never
    {
        throw new RedirectException(['u' => $url, 'spa' => $spa]);
    }

    /**
     * @param list<mixed> $params
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
     * @param string|list<string> $components
     * @param list<mixed> $params
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
     * @param list<mixed> $params
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

    protected function dispatchBrowserEvent(string $event, mixed $data = null): self
    {
        $this->browserEvents[] = ['name' => $event, 'data' => $data];

        return $this;
    }

    /**
     * @return list<array{name: string, params: list<mixed>, to: ?list<string>, self: bool}>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * @return list<array{name: string, data: mixed}>
     */
    public function getBrowserEvents(): array
    {
        return $this->browserEvents;
    }

    public function clearDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
        $this->browserEvents = [];
    }

    public function dehydrateForResponse(): void
    {
        $this->dehydrate();
    }

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
