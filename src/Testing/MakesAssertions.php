<?php

namespace Doppar\Ripple\Testing;

use PHPUnit\Framework\Assert as PHPUnit;

trait MakesAssertions
{
    public function assertSee(string|array $values, bool $escape = true): self
    {
        $html = $this->html();
        foreach ($this->wrap($values) as $value) {
            $needle = $escape ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : (string) $value;
            PHPUnit::assertStringContainsString($needle, $html);
        }

        return $this;
    }

    public function assertDontSee(string|array $values, bool $escape = true): self
    {
        $html = $this->html();
        foreach ($this->wrap($values) as $value) {
            $needle = $escape ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : (string) $value;
            PHPUnit::assertStringNotContainsString($needle, $html);
        }

        return $this;
    }

    public function assertSeeHtml(string|array $values): self
    {
        return $this->assertSee($values, escape: false);
    }

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

    public function assertSetStrict(string $name, mixed $value): self
    {
        return $this->assertSet($name, $value, true);
    }

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

    public function assertOk(): self
    {
        PHPUnit::assertTrue((bool) ($this->effects()['ok'] ?? false));

        return $this;
    }

    public function assertNotOk(): self
    {
        PHPUnit::assertFalse((bool) ($this->effects()['ok'] ?? true));

        return $this;
    }

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

    public function assertRedirect(?string $url = null): self
    {
        $response = $this->effects() ?? [];
        PHPUnit::assertArrayHasKey('rd', $response, 'Expected a redirect response.');

        if ($url !== null) {
            PHPUnit::assertSame($url, $response['rd']['u'] ?? null);
        }

        return $this;
    }

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

    public function assertNotDispatched(string $event): self
    {
        $events = $this->effects()['ev'] ?? [];
        PHPUnit::assertIsArray($events);

        foreach ($events as $item) {
            PHPUnit::assertNotSame($event, $item['name'] ?? null, "Unexpected dispatched event [{$event}].");
        }

        return $this;
    }

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
     * @param string|array<int|string, mixed> $values
     * @return list<mixed>
     */
    private function wrap(string|array $values): array
    {
        return is_array($values) ? array_values($values) : [$values];
    }
}
