<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Attributes\Computed;
use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Attributes\On;
use Doppar\Ripple\Attributes\QueryString;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
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
            if (!$property->isInitialized($this)) {
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
            if (!property_exists($this, $property)) {
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
     */
    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            if (in_array($key, $this->hidden, true) || in_array($key, $this->computed, true)) {
                continue;
            }
            $property = new ReflectionProperty($this, $key);
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }
            $property->setValue($this, $value);
        }

        $this->hydrateLifecycle();
    }

    public function updateProperty(string $property, mixed $value): void
    {
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException("Property [{$property}] does not exist on " . static::class);
        }
        if (in_array($property, $this->hidden, true) || in_array($property, $this->computed, true)) {
            throw new \InvalidArgumentException("Property [{$property}] cannot be updated from the client.");
        }
        if (in_array($property, $this->locked, true)) {
            throw new \InvalidArgumentException("Property [{$property}] is locked.");
        }

        $this->updating($property, $value);

        // Drop stale field errors so live bind can patch them out of the DOM.
        $this->resetValidation($property);

        $ref = new ReflectionProperty($this, $property);
        $type = $ref->getType();
        if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && !is_array($value)) {
            $value = $value !== null ? [$value] : [];
        }

        $ref->setValue($this, $value);
        $this->updated($property);

        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }
    }

    /**
     * @param list<mixed> $args
     */
    public function callAction(?string $action, array $args = []): mixed
    {
        if ($action === null || $action === '') {
            return null;
        }

        if (!method_exists($this, $action)) {
            throw new \BadMethodCallException("Ripple action [{$action}] does not exist on " . static::class);
        }

        $method = new ReflectionMethod($this, $action);
        if (!$method->isPublic() || $method->isStatic()) {
            throw new \BadMethodCallException("Ripple action [{$action}] must be public on " . static::class);
        }

        if ($method->getAttributes(Invokable::class) === []) {
            throw new \BadMethodCallException(
                "Ripple action [{$action}] is not invokable. Add #[Invokable] on " . static::class
            );
        }

        return $this->{$action}(...$args);
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
     * @return array<string, mixed|null>
     */
    public function getQueryString(): array
    {
        $query = [];
        foreach ($this->urlProperties as $property => $config) {
            $value = property_exists($this, $property) ? $this->{$property} : null;
            // null ⇒ client should delete the param (keep empty keys out of the URL)
            if (!$config['keep'] && ($value === null || $value === '')) {
                $query[$config['as']] = null;
                continue;
            }
            $query[$config['as']] = $value;
        }

        return $query;
    }

    /**
     * @return array<string, string|list<string>>
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
     * @param array<string, string|list<string>>|null $rules
     * @return array<string, mixed>
     */
    protected function validate(?array $rules = null): array
    {
        $rules ??= $this->rules();
        $state = $this->getState();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $ruleList = is_array($rule) ? $rule : explode('|', (string) $rule);
            $value = $state[$field] ?? null;
            foreach ($ruleList as $r) {
                $r = trim((string) $r);
                if ($r === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = $this->messages()["{$field}.required"] ?? "The {$field} field is required.";
                }
                if (str_starts_with($r, 'min:') && is_string($value)) {
                    $min = (int) substr($r, 4);
                    if (mb_strlen($value) < $min) {
                        $errors[$field][] = $this->messages()["{$field}.min"] ?? "The {$field} must be at least {$min} characters.";
                    }
                }
                if ($r === 'email' && $value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = $this->messages()["{$field}.email"] ?? "The {$field} must be a valid email.";
                }
            }
        }

        if ($errors !== []) {
            $this->errorBag = $errors;
            throw new ValidationException($errors);
        }

        $this->resetValidation();

        return array_intersect_key($state, $rules);
    }

    protected function validateOnly(string $field): void
    {
        $rules = $this->rules();
        if (!isset($rules[$field])) {
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
