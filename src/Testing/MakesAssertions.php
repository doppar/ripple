<?php

namespace Doppar\Ripple\Testing;

use PHPUnit\Framework\Assert as PHPUnit;

trait MakesAssertions
{
    /**
     * Assert that the rendered HTML contains the given value(s).
     *
     * @param string|array $values
     * @param bool $escape
     * @return self
     */
    public function assertSee(string|array $values, bool $escape = true): self
    {
        $html = $this->html();
        foreach ($this->wrap($values) as $value) {
            $needle = $escape ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : (string) $value;
            PHPUnit::assertStringContainsString($needle, $html);
        }

        return $this;
    }

    /**
     * Assert that the rendered HTML does not contain the given value(s).
     *
     * @param string|array $values
     * @param bool $escape
     * @return self
     */
    public function assertDontSee(string|array $values, bool $escape = true): self
    {
        $html = $this->html();
        foreach ($this->wrap($values) as $value) {
            $needle = $escape ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : (string) $value;
            PHPUnit::assertStringNotContainsString($needle, $html);
        }

        return $this;
    }

    /**
     * Assert that the rendered HTML contains the given unescaped value(s).
     *
     * @param string|array $values
     * @return self
     */
    public function assertSeeHtml(string|array $values): self
    {
        return $this->assertSee($values, escape: false);
    }

    /**
     * Assert that a component property equals the given value or passes the given callback.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $strict
     * @return self
     */
    public function assertSet(string $name, mixed $value, bool $strict = false): self
    {
        $actual = $this->get($name);

        if (! is_string($value) && is_callable($value)) {
            PHPUnit::assertTrue($value($actual));
        } elseif ($strict) {
            PHPUnit::assertSame($value, $actual);
        } else {
            PHPUnit::assertEquals($value, $actual);
        }

        return $this;
    }

    /**
     * Assert that a component property does not equal the given value.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $strict
     * @return self
     */
    public function assertNotSet(string $name, mixed $value, bool $strict = false): self
    {
        $actual = $this->get($name);

        if ($strict) {
            PHPUnit::assertNotSame($value, $actual);
        } else {
            PHPUnit::assertNotEquals($value, $actual);
        }

        return $this;
    }

    /**
     * Assert that a component property is strictly identical to the given value.
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function assertSetStrict(string $name, mixed $value): self
    {
        return $this->assertSet($name, $value, true);
    }

    /**
     * Assert that the view data has the given key, optionally matching a value or callback.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function assertViewHas(string $key, mixed $value = null): self
    {
        $data = $this->viewData();
        PHPUnit::assertIsArray($data);
        PHPUnit::assertArrayHasKey($key, $data);

        if (func_num_args() === 1) {
            return $this;
        }

        if (! is_string($value) && is_callable($value)) {
            PHPUnit::assertTrue($value($data[$key]));
        } else {
            PHPUnit::assertEquals($value, $data[$key]);
        }

        return $this;
    }

    /**
     * Assert that the last response was successful.
     *
     * @return self
     */
    public function assertOk(): self
    {
        PHPUnit::assertTrue((bool) ($this->effects()['ok'] ?? false));

        return $this;
    }

    /**
     * Assert that the last response was not successful.
     *
     * @return self
     */
    public function assertNotOk(): self
    {
        PHPUnit::assertFalse((bool) ($this->effects()['ok'] ?? true));

        return $this;
    }

    /**
     * Assert that validation errors exist, optionally for the given keys.
     *
     * @param string|array|null $keys
     * @return self
     */
    public function assertHasErrors(string|array|null $keys = null): self
    {
        $errors = $this->errors();
        PHPUnit::assertNotEmpty($errors, 'Expected validation errors, got none.');

        if ($keys === null) {
            return $this;
        }

        foreach ($this->wrap($keys) as $key) {
            PHPUnit::assertArrayHasKey($key, $errors, "Expected errors for [{$key}].");
            PHPUnit::assertNotEmpty($errors[$key]);
        }

        return $this;
    }

    /**
     * Assert that no validation errors exist, optionally for the given keys.
     *
     * @param string|array|null $keys
     * @return self
     */
    public function assertHasNoErrors(string|array|null $keys = null): self
    {
        $errors = $this->errors();

        if ($keys === null) {
            PHPUnit::assertSame([], $errors);

            return $this;
        }

        foreach ($this->wrap($keys) as $key) {
            PHPUnit::assertTrue(
                ! isset($errors[$key]) || $errors[$key] === [],
                "Unexpected errors for [{$key}].",
            );
        }

        return $this;
    }

    /**
     * Assert that the last response is a redirect, optionally to the given URL.
     *
     * @param string|null $url
     * @return self
     */
    public function assertRedirect(?string $url = null): self
    {
        $response = $this->effects() ?? [];
        PHPUnit::assertArrayHasKey('rd', $response, 'Expected a redirect response.');

        if ($url !== null) {
            PHPUnit::assertSame($url, $response['rd']['u'] ?? null);
        }

        return $this;
    }

    /**
     * Assert that the given event was dispatched, optionally with matching params.
     *
     * @param string $event
     * @param mixed $params
     * @return self
     */
    public function assertDispatched(string $event, mixed $params = null): self
    {
        $events = $this->effects()['ev'] ?? [];
        PHPUnit::assertIsArray($events);

        $match = null;
        foreach ($events as $item) {
            if (($item['name'] ?? null) === $event) {
                $match = $item;
                break;
            }
        }

        PHPUnit::assertNotNull($match, "Expected dispatched event [{$event}].");

        if ($params !== null) {
            if (is_callable($params)) {
                PHPUnit::assertTrue($params($match['params'] ?? []));
            } else {
                PHPUnit::assertEquals(
                    is_array($params) ? array_values($params) : [$params],
                    $match['params'] ?? [],
                );
            }
        }

        return $this;
    }

    /**
     * Assert that the given event was not dispatched.
     *
     * @param string $event
     * @return self
     */
    public function assertNotDispatched(string $event): self
    {
        $events = $this->effects()['ev'] ?? [];
        PHPUnit::assertIsArray($events);

        foreach ($events as $item) {
            PHPUnit::assertNotSame($event, $item['name'] ?? null, "Unexpected dispatched event [{$event}].");
        }

        return $this;
    }

    /**
     * Assert that the given browser event was dispatched, optionally with matching data.
     *
     * @param string $event
     * @param mixed $data
     * @return self
     */
    public function assertBrowserEvent(string $event, mixed $data = null): self
    {
        $events = $this->effects()['be'] ?? [];
        PHPUnit::assertIsArray($events);

        $match = null;
        foreach ($events as $item) {
            if (($item['name'] ?? null) === $event) {
                $match = $item;
                break;
            }
        }

        PHPUnit::assertNotNull($match, "Expected browser event [{$event}].");

        if (func_num_args() === 2) {
            if (is_callable($data)) {
                PHPUnit::assertTrue($data($match['data'] ?? null));
            } else {
                PHPUnit::assertEquals($data, $match['data'] ?? null);
            }
        }

        return $this;
    }

    /**
     * Wrap the given value in a list, or reindex it if already an array.
     *
     * @param string|array<int|string, mixed> $values
     * @return list<mixed>
     */
    private function wrap(string|array $values): array
    {
        return is_array($values) ? array_values($values) : [$values];
    }
}
