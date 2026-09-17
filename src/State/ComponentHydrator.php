<?php

namespace Doppar\Ripple\State;

use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Component;
use ReflectionClass;

final class ComponentHydrator
{
    public function __construct(private readonly StateSigner $signer = new StateSigner('ripple-dev-key'))
    {
    }

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $params
     */
    public function mount(string $class, array $params = [], ?string $id = null): Component
    {
        /** @var Component $instance */
        $instance = new $class();
        if ($id !== null && $id !== '') {
            $instance->setId($id);
        }
        if ($params !== []) {
            $instance->hydrate($params);
        }
        $instance->hydrateFromQueryString();
        $instance->mount();

        return $instance;
    }

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $state
     */
    public function hydrate(string $class, array $state, string $id): Component
    {
        /** @var Component $instance */
        $instance = new $class();
        $instance->setId($id);
        $instance->hydrate($state);

        return $instance;
    }

    public function isLazy(string $class): bool
    {
        return (new ReflectionClass($class))->getAttributes(Lazy::class) !== [];
    }

    public function lazyAttribute(string $class): ?Lazy
    {
        $attrs = (new ReflectionClass($class))->getAttributes(Lazy::class);
        if ($attrs === []) {
            return null;
        }

        /** @var Lazy */
        return $attrs[0]->newInstance();
    }

    public function signer(): StateSigner
    {
        return $this->signer;
    }
}
