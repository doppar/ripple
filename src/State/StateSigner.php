<?php

namespace Doppar\Ripple\State;

use Doppar\Ripple\Support\Config;

final class StateSigner
{
    /**
     * The built-in key used only for local development and tests. It is public, so it signs nothing real.
     *
     * @var string
     */
    public const DEV_KEY = 'ripple-dev-key';

    /**
     * Environments allowed to fall back to the built-in development key.
     *
     * @var list<string>
     */
    private const DEV_ENVIRONMENTS = ['local', 'development', 'dev', 'testing', 'test'];

    /**
     * Minimum length of a configured signing key outside development environments.
     *
     * @var int
     */
    public const MIN_KEY_LENGTH = 32;

    /**
     * Create a new state signer instance.
     *
     * @param string $key
     */
    public function __construct(private readonly string $key)
    {
    }

    /**
     * Create a signer from the configured signing key.
     *
     * Fails closed: the built-in development key is used only when the environment is
     * explicitly a development or testing one. Any other environment, including an unset
     * one, must configure a key of at least MIN_KEY_LENGTH characters.
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        $key = self::firstKey(
            Config::get('ripple.security.signing_key'),
            Config::get('app.key'),
            function_exists('env') ? env('APP_KEY') : null,
        );

        $isDev = self::isDevelopment();

        if ($key === '' || $key === self::DEV_KEY) {
            if (! $isDev) {
                throw new \RuntimeException(
                    'Ripple signing key is not configured. Set RIPPLE_SIGNING_KEY or APP_KEY. '
                    . 'The built-in development key is only used when APP_ENV is one of: '
                    . implode(', ', self::DEV_ENVIRONMENTS) . '.',
                );
            }

            return new self(self::DEV_KEY);
        }

        if (! $isDev && strlen($key) < self::MIN_KEY_LENGTH) {
            throw new \RuntimeException(sprintf(
                'Ripple signing key is too short. Use at least %d characters for RIPPLE_SIGNING_KEY or APP_KEY.',
                self::MIN_KEY_LENGTH,
            ));
        }

        return new self($key);
    }

    /**
     * Return the first candidate that is not blank, unchanged, or an empty string.
     *
     * An empty RIPPLE_SIGNING_KEY must not hide a real APP_KEY behind it.
     *
     * @param mixed ...$candidates
     * @return string
     */
    private static function firstKey(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Determine whether the application is explicitly running in a development or testing environment.
     *
     * An unset or unrecognised environment counts as deployed, so it never gets the public dev key.
     *
     * @return bool
     */
    private static function isDevelopment(): bool
    {
        $env = Config::get('app.env')
            ?? (function_exists('env') ? env('APP_ENV') : null);

        if (! is_string($env)) {
            return false;
        }

        return in_array(strtolower(trim($env)), self::DEV_ENVIRONMENTS, true);
    }

    /**
     * Compute the HMAC signature binding a component's state to its id and class.
     *
     * @param array<string, mixed> $state
     * @param string $componentId
     * @param string $class
     * @return string
     */
    public function sign(array $state, string $componentId, string $class): string
    {
        return hash_hmac('sha256', $this->payload($state, $componentId, $class), $this->key);
    }

    /**
     * Verify that a signature matches the component's state, id and class.
     *
     * @param array<string, mixed> $state
     * @param string $componentId
     * @param string $class
     * @param string $signature
     * @return bool
     */
    public function verify(array $state, string $componentId, string $class, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->sign($state, $componentId, $class), $signature);
    }

    /**
     * Compute the HMAC signature binding a lazy placeholder's class, id and mount params.
     *
     * @param string $class
     * @param array<string, mixed> $params
     * @param string $componentId
     * @return string
     */
    public function signLazy(string $class, array $params, string $componentId): string
    {
        // A key derived only for lazy placeholders: no state signature, whatever its id, can ever match.
        $lazyKey = hash_hmac('sha256', 'ripple-lazy', $this->key);

        return hash_hmac('sha256', $this->lazyPayload($class, $params, $componentId), $lazyKey);
    }

    /**
     * Verify that a lazy signature matches the placeholder's class, id and mount params.
     *
     * @param string $class
     * @param array<string, mixed> $params
     * @param string $componentId
     * @param string $signature
     * @return bool
     */
    public function verifyLazy(string $class, array $params, string $componentId, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->signLazy($class, $params, $componentId), $signature);
    }

    /**
     * Compute an MD5 fingerprint of the normalized state.
     *
     * @param array<string, mixed> $state
     * @return string
     */
    public function fingerprint(array $state): string
    {
        $json = json_encode($this->normalize($state), JSON_THROW_ON_ERROR);

        return md5($json);
    }

    /**
     * Build the string payload that gets signed for a component's state.
     *
     * The class is part of the payload so state signed for one component cannot be replayed
     * against another. Fields are JSON-encoded so no id or class name can shift bytes between them.
     *
     * @param array<string, mixed> $state
     * @param string $componentId
     * @param string $class
     * @return string
     */
    private function payload(array $state, string $componentId, string $class): string
    {
        return json_encode(
            ['state', $componentId, ltrim($class, '\\'), $this->normalize($state)],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Build the string payload that gets signed for a lazy placeholder.
     *
     * Fields are JSON-encoded so no id or class name can shift bytes between them.
     *
     * @param string $class
     * @param array<string, mixed> $params
     * @param string $componentId
     * @return string
     */
    private function lazyPayload(string $class, array $params, string $componentId): string
    {
        return json_encode(
            [$componentId, ltrim($class, '\\'), $this->normalize($params)],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Sort the state by key so encoding is deterministic.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function normalize(array $state): array
    {
        ksort($state);

        return $state;
    }
}
