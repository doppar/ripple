<?php

namespace Doppar\Ripple\State;

use Doppar\Ripple\Support\Config;

final class StateSigner
{
    public function __construct(private readonly string $key)
    {
    }

    public static function fromConfig(): self
    {
        $key = (string) (Config::get('ripple.security.signing_key')
            ?? Config::get('app.key')
            ?? (function_exists('env') ? env('APP_KEY') : null)
            ?? 'ripple-dev-key');

        return new self($key !== '' ? $key : 'ripple-dev-key');
    }

    /**
     * @param array<string, mixed> $state
     */
    public function sign(array $state, string $componentId): string
    {
        return hash_hmac('sha256', $this->payload($state, $componentId), $this->key);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function verify(array $state, string $componentId, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->sign($state, $componentId), $signature);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function fingerprint(array $state): string
    {
        $json = json_encode($this->normalize($state), JSON_THROW_ON_ERROR);

        return md5($json);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function payload(array $state, string $componentId): string
    {
        return $componentId . '|' . json_encode($this->normalize($state), JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function normalize(array $state): array
    {
        ksort($state);

        return $state;
    }
}
