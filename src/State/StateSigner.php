<?php

namespace Doppar\Ripple\State;

use Doppar\Ripple\Support\Config;

final class StateSigner
{
    /**
     * Create a new state signer instance.
     *
     * @param string $key
     */
    public function __construct(private readonly string $key)
    {
    }

    /**
     * Create a signer from the configured signing key, falling back to a dev key outside production.
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        $configured = Config::get('ripple.security.signing_key')
            ?? Config::get('app.key')
            ?? (function_exists('env') ? env('APP_KEY') : null);

        $key = is_string($configured) ? $configured : '';
        if ($key === '' || $key === 'ripple-dev-key') {
            if (self::isProduction()) {
                throw new \RuntimeException(
                    'Ripple signing key is not configured. Set RIPPLE_SIGNING_KEY or APP_KEY.',
                );
            }

            return new self('ripple-dev-key');
        }

        return new self($key);
    }

    /**
     * Determine whether the application is running in production.
     *
     * @return bool
     */
    private static function isProduction(): bool
    {
        $env = Config::get('app.env')
            ?? (function_exists('env') ? env('APP_ENV') : null)
            ?? 'local';

        return in_array(strtolower((string) $env), ['production', 'prod'], true);
    }

    /**
     * Compute the HMAC signature for a component's state.
     *
     * @param array<string, mixed> $state
     * @param string $componentId
     * @return string
     */
    public function sign(array $state, string $componentId): string
    {
        return hash_hmac('sha256', $this->payload($state, $componentId), $this->key);
    }

    /**
     * Verify that a signature matches the component's state.
     *
     * @param array<string, mixed> $state
     * @param string $componentId
     * @param string $signature
     * @return bool
     */
    public function verify(array $state, string $componentId, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->sign($state, $componentId), $signature);
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
     * @param array<string, mixed> $state
     * @param string $componentId
     * @return string
     */
    private function payload(array $state, string $componentId): string
    {
        return $componentId . '|' . json_encode($this->normalize($state), JSON_THROW_ON_ERROR);
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
