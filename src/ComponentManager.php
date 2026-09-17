<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Delta\DiffEngine;
use Doppar\Ripple\VDom\HtmlParser;

final class ComponentManager
{
    /** @var array<string, class-string<Component>> */
    private array $aliases = [];

    public function __construct(
        private readonly HtmlParser $parser = new HtmlParser(),
        private readonly DiffEngine $diff = new DiffEngine(),
    ) {}

    /**
     * @param class-string<Component> $class
     */
    public function register(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function mount(string $component, array $params = []): string
    {
        $instance = $this->make($component, $params);
        $instance->mount();

        $id = bin2hex(random_bytes(8));
        $name = $instance::class;
        $state = $instance->getState();
        $inner = $instance->render();

        return sprintf(
            '<div data-ripple data-ripple-id="%s" data-ripple-name="%s" data-ripple-state="%s">%s</div>',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars(json_encode($state, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $inner
        );
    }

    /**
     * Handle an update intent and return a compact wire payload.
     *
     * Request keys: i=id, n=name, a=action, g=args, s=state
     * Response keys: i, n, d=deltas, s=state
     *
     * @param array{i?: string, n?: string, a?: ?string, g?: array, s?: array} $payload
     * @return array{i: string, n: string, d: list<array<string, mixed>>, s: array<string, mixed>}
     */
    public function update(array $payload): array
    {
        $name = (string) ($payload['n'] ?? '');
        $action = $payload['a'] ?? null;
        $args = $payload['g'] ?? [];
        $state = $payload['s'] ?? [];
        $id = (string) ($payload['i'] ?? '');

        if ($name === '') {
            throw new \InvalidArgumentException('Ripple update requires a component name.');
        }

        $instance = $this->make($name);
        $instance->hydrate(is_array($state) ? $state : []);

        $oldHtml = $instance->render();
        $oldTree = $this->parser->parse($oldHtml);

        $instance->callAction(is_string($action) ? $action : null, is_array($args) ? $args : []);

        $newHtml = $instance->render();
        $newTree = $this->parser->parse($newHtml);

        $patch = $this->diff->diff($oldTree, $newTree);

        return [
            'i' => $id,
            'n' => $name,
            'd' => $patch->toArray(),
            's' => $instance->getState(),
        ];
    }

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function make(string $component, array $params = []): Component
    {
        $class = $this->resolveClass($component);

        if (!is_subclass_of($class, Component::class)) {
            throw new \InvalidArgumentException("[{$class}] must extend " . Component::class);
        }

        /** @var Component $instance */
        $instance = new $class();

        if ($params !== []) {
            $instance->hydrate($params);
        }

        return $instance;
    }

    /**
     * @return class-string<Component>
     */
    private function resolveClass(string $component): string
    {
        if (isset($this->aliases[$component])) {
            return $this->aliases[$component];
        }

        if (class_exists($component)) {
            /** @var class-string<Component> $component */
            return $component;
        }

        $namespaced = rtrim((string) config('ripple.component_namespace', 'App\\Ripple\\'), '\\') . '\\' . $component;
        if (class_exists($namespaced)) {
            /** @var class-string<Component> $namespaced */
            return $namespaced;
        }

        throw new \InvalidArgumentException("Ripple component [{$component}] was not found.");
    }

    public function scripts(): string
    {
        $src = '/vendor/ripple/ripple.js';
        $uri = htmlspecialchars((string) config('ripple.update_uri', '/ripple/update'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<meta name="ripple-update" content="' . $uri . '">'
            . '<script src="' . $src . '" defer></script>';
    }

    public function parser(): HtmlParser
    {
        return $this->parser;
    }

    public function diffEngine(): DiffEngine
    {
        return $this->diff;
    }
}
