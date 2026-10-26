<?php

namespace Doppar\Ripple\State;

use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Component;
use ReflectionClass;

final class ComponentHydrator
{
    /**
     * Create a new component hydrator instance.
     *
     * @param StateSigner $signer
     */
    public function __construct(private readonly StateSigner $signer = new StateSigner('ripple-dev-key'))
    {
    }

    /**
     * Instantiate a component, apply trusted mount params and query string state, then mount it.
     *
     * @param class-string<Component> $class
     * @param array<string, mixed> $params
     * @param string|null $id
     * @return Component
     */
    public function mount(string $class, array $params = [], ?string $id = null): Component
    {
        /** @var Component $instance */
        $instance = new $class();
        if ($id !== null && $id !== '') {
            $instance->setId($id);
        }
        if ($params !== []) {
            // Server-side mount params are trusted (routes / Ripple::mount).
            $instance->hydrate($params, allowLocked: true);
        }
        $instance->hydrateFromQueryString();
        $instance->mount();

        return $instance;
    }

    /**
     * Instantiate a component and restore its state from a client snapshot.
     *
     * @param class-string<Component> $class
     * @param array<string, mixed> $state
     * @param string $id
     * @param bool $allowLocked
     * @return Component
     */
    public function hydrate(string $class, array $state, string $id, bool $allowLocked = false): Component
    {
        /** @var Component $instance */
        $instance = new $class();
        $instance->setId($id);
        $instance->hydrate($state, $allowLocked);

        return $instance;
    }

    /**
     * Determine whether the component class is marked with the Lazy attribute.
     *
     * @param string $class
     * @return bool
     */
    public function isLazy(string $class): bool
    {
        return (new ReflectionClass($class))->getAttributes(Lazy::class) !== [];
    }

    /**
     * Get the Lazy attribute instance for the component class, if present.
     *
     * @param string $class
     * @return Lazy|null
     */
    public function lazyAttribute(string $class): ?Lazy
    {
        $attrs = (new ReflectionClass($class))->getAttributes(Lazy::class);
        if ($attrs === []) {
            return null;
        }

        /** @var Lazy */
        return $attrs[0]->newInstance();
    }

    /**
     * Get the state signer used by this hydrator.
     *
     * @return StateSigner
     */
    public function signer(): StateSigner
    {
        return $this->signer;
    }
}
