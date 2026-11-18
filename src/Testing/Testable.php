<?php

namespace Doppar\Ripple\Testing;

use Doppar\Ripple\Component;
use Doppar\Ripple\ComponentManager;

/**
 * @template TComponent of Component
 */
final class Testable
{
    use MakesAssertions;

    /**
     * The class name of the component under test.
     *
     * @var class-string<Component>
     */
    private string $name;

    /**
     * The component instance ID.
     *
     * @var string
     */
    private string $id = '';

    /**
     * The current serialized component state.
     *
     * @var array<string, mixed>
     */
    private array $state = [];

    /**
     * The fingerprint of the current state.
     *
     * @var string
     */
    private string $fingerprint = '';

    /**
     * The signature of the current state.
     *
     * @var string
     */
    private string $signature = '';

    /**
     * The most recently rendered component HTML.
     *
     * @var string
     */
    private string $html = '';

    /**
     * The current validation errors keyed by field.
     *
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * The last mount or update response payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $lastResponse = null;

    /**
     * Create a testable instance and mount the given component.
     *
     * @param ComponentManager $manager
     * @param class-string<Component> $component
     * @param array<string, mixed> $params
     * @return self
     */
    public static function create(ComponentManager $manager, string $component, array $params = []): self
    {
        $testable = new self($manager);
        $testable->boot($component, $params);

        return $testable;
    }

    /**
     * Create a new testable instance.
     *
     * @param ComponentManager $manager
     */
    private function __construct(private readonly ComponentManager $manager)
    {
    }

    /**
     * Mount the component, resolving lazy components, and capture its initial state.
     *
     * @param class-string<Component> $component
     * @param array<string, mixed> $params
     * @return void
     */
    private function boot(string $component, array $params): void
    {
        $this->name = $component;
        $wrapped = $this->manager->mount($component, $params);

        if (str_contains($wrapped, 'data-ripple-lazy')) {
            $id = $this->attr($wrapped, 'data-ripple-id');
            $result = $this->manager->loadLazy($component, $params, $id !== '' ? $id : null);
            $this->absorbLazyBoot($result);

            return;
        }

        $this->applyMountHtml($wrapped);
        $this->lastResponse = [
            'ok' => true,
            'i' => $this->id,
            'n' => $this->name,
            's' => $this->state,
            'f' => $this->fingerprint,
            'x' => $this->signature,
            'd' => [],
            'e' => [],
        ];
    }

    /**
     * Absorb the result of loading a lazy component as the initial state.
     *
     * @param array<string, mixed> $result
     * @return void
     */
    private function absorbLazyBoot(array $result): void
    {
        $this->id = (string) ($result['i'] ?? '');
        $this->name = (string) ($result['n'] ?? $this->name);
        /** @var array<string, mixed> $state */
        $state = is_array($result['s'] ?? null) ? $result['s'] : [];
        $this->state = $state;
        $this->fingerprint = (string) ($result['f'] ?? '');
        $this->signature = (string) ($result['x'] ?? '');
        $this->html = is_string($result['h'] ?? null)
            ? $result['h']
            : $this->manager->html($this->instance());
        $this->lastResponse = array_merge([
            'ok' => true,
            'd' => [],
            'e' => [],
        ], $result);
    }

    /**
     * Call an invokable method on the component.
     *
     * @param string $method
     * @param mixed ...$params
     * @return self
     */
    public function call(string $method, mixed ...$params): self
    {
        return $this->sendUpdate([
            't' => 'a',
            'a' => $method,
            'g' => array_values($params),
        ]);
    }

    /**
     * Set one or more component properties through an update request.
     *
     * @param string|array $name
     * @param mixed $value
     * @return self
     */
    public function set(string|array $name, mixed $value = null): self
    {
        if (is_array($name)) {
            foreach ($name as $key => $item) {
                $this->set((string) $key, $item);
            }

            return $this;
        }

        return $this->sendUpdate([
            't' => 'u',
            'p' => $name,
            'v' => $value,
        ]);
    }

    /**
     * Set multiple component properties at once.
     *
     * @param array<string, mixed> $values
     * @return self
     */
    public function fill(array $values): self
    {
        return $this->set($values);
    }

    /**
     * Toggle a boolean component property.
     *
     * @param string $name
     * @return self
     */
    public function toggle(string $name): self
    {
        return $this->set($name, ! $this->get($name));
    }

    /**
     * Re-render the component HTML from the current state.
     *
     * @return self
     */
    public function refresh(): self
    {
        $this->html = $this->manager->html($this->instance());

        return $this;
    }

    /**
     * Get a component property value using dot notation.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->valueAt($this->instance(), $key);
    }

    /**
     * Get the most recently rendered component HTML.
     *
     * @return string
     */
    public function html(): string
    {
        return $this->html;
    }

    /**
     * Hydrate a fresh component instance from the current state.
     *
     * @return Component
     */
    public function instance(): Component
    {
        $instance = $this->manager->hydrateForTest($this->name, $this->state, $this->id);
        if ($this->errors !== []) {
            $instance->setErrorBag($this->errors);
        }

        return $instance;
    }

    /**
     * Get the component's view data, or a single key from it.
     *
     * @param string|null $key
     * @return mixed
     */
    public function viewData(?string $key = null): mixed
    {
        $data = $this->instance()->viewData();
        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }

    /**
     * Get the current validation errors.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the last response payload.
     *
     * @return array<string, mixed>|null
     */
    public function effects(): ?array
    {
        return $this->lastResponse;
    }

    /**
     * Get the component instance ID.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Send an update request with the current state and absorb the response.
     *
     * @param array<string, mixed> $extra
     * @return self
     */
    private function sendUpdate(array $extra): self
    {
        $payload = array_merge([
            'i' => $this->id,
            'n' => $this->name,
            's' => $this->state,
            'f' => $this->fingerprint,
            'x' => $this->signature,
        ], $extra);

        if ($this->errors !== []) {
            $payload['er'] = $this->errors;
        }

        $response = $this->manager->update($payload);
        $this->absorbResponse($response);

        return $this;
    }

    /**
     * Sync state, signature, errors and HTML from an update response.
     *
     * @param array<string, mixed> $response
     * @return void
     */
    private function absorbResponse(array $response): void
    {
        $this->lastResponse = $response;

        if (isset($response['s']) && is_array($response['s'])) {
            /** @var array<string, mixed> $state */
            $state = $response['s'];
            $this->state = $state;
        }

        if (isset($response['i']) && is_string($response['i']) && $response['i'] !== '') {
            $this->id = $response['i'];
        }

        if (isset($response['f']) && is_string($response['f'])) {
            $this->fingerprint = $response['f'];
        }

        if (isset($response['x']) && is_string($response['x']) && $response['x'] !== '') {
            $this->signature = $response['x'];
        } else {
            $this->signature = $this->manager->signState($this->state, $this->id, $this->name);
            $this->fingerprint = $this->manager->signer()->fingerprint($this->state);
        }

        if (array_key_exists('e', $response) && is_array($response['e'])) {
            /** @var array<string, list<string>> $errors */
            $errors = $response['e'];
            $this->errors = $errors;
        } else {
            $this->errors = [];
        }

        // Redirects skip re-render; keep prior HTML for assertSee, still sync state.
        if (isset($response['rd'])) {
            return;
        }

        $this->html = $this->manager->html($this->instance());
    }

    /**
     * Extract the ID, signature and state from mounted component HTML.
     *
     * @param string $wrapped
     * @return void
     *
     * @throws \RuntimeException
     */
    private function applyMountHtml(string $wrapped): void
    {
        $this->id = $this->attr($wrapped, 'data-ripple-id');
        $this->fingerprint = $this->attr($wrapped, 'data-ripple-f');
        $this->signature = $this->attr($wrapped, 'data-ripple-x');

        $stateJson = htmlspecialchars_decode($this->attr($wrapped, 'data-ripple-state'), ENT_QUOTES);
        $decoded = json_decode($stateJson, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Failed to parse Ripple mount state for testing.');
        }
        /** @var array<string, mixed> $decoded */
        $this->state = $decoded;

        $name = $this->attr($wrapped, 'data-ripple-name');
        if ($name !== '') {
            $this->name = $name;
        }

        $this->html = $this->manager->html($this->instance());
    }

    /**
     * Extract and decode an attribute value from the given HTML.
     *
     * @param string $html
     * @param string $name
     * @return string
     */
    private function attr(string $html, string $name): string
    {
        $pattern = '/' . preg_quote($name, '/') . '="([^"]*)"/';
        if (! preg_match($pattern, $html, $m)) {
            return '';
        }

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Resolve a dot-notation key against an object or array.
     *
     * @param object|array $target
     * @param string $key
     * @return mixed
     */
    private function valueAt(object|array $target, string $key): mixed
    {
        if ($key === '') {
            return $target;
        }

        $segments = explode('.', $key);
        $current = $target;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (! array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
                continue;
            }

            if (is_object($current) && (isset($current->{$segment}) || property_exists($current, $segment))) {
                $current = $current->{$segment};
                continue;
            }

            return null;
        }

        return $current;
    }
}
