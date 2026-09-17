<?php

namespace Doppar\Ripple;

use ReflectionClass;
use ReflectionProperty;

abstract class Component
{
    public function mount(): void
    {
        //
    }

    abstract public function render(): string;

    /**
     * Public property bag used as component state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = [];
        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            if ($property->isInitialized($this)) {
                $state[$name] = $property->getValue($this);
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function hydrate(array $state): void
    {
        $ref = new ReflectionClass($this);

        foreach ($state as $key => $value) {
            if (!$ref->hasProperty($key)) {
                continue;
            }
            $property = $ref->getProperty($key);
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }
            $property->setValue($this, $value);
        }
    }

    public function callAction(?string $action, array $args = []): void
    {
        if ($action === null || $action === '') {
            return;
        }

        if (!method_exists($this, $action)) {
            throw new \BadMethodCallException("Ripple action [{$action}] does not exist on " . static::class);
        }

        if ((new ReflectionClass($this))->getMethod($action)->isPublic() === false) {
            throw new \BadMethodCallException("Ripple action [{$action}] must be public on " . static::class);
        }

        $this->{$action}(...$args);
    }
}
