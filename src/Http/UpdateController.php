<?php

namespace Doppar\Ripple\Http;

use Doppar\Ripple\ComponentManager;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response\JsonResponse;

final class UpdateController
{
    public function __construct(private readonly ComponentManager $manager) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        try {
            $result = $this->manager->update($payload);
        } catch (\Throwable $e) {
            return new JsonResponse(['e' => $e->getMessage()], 422);
        }

        return new JsonResponse($result);
    }

    /**
     * Wire keys: i=id, n=name, a=action, g=args, s=state
     *
     * @return array{i?: string, n?: string, a?: ?string, g?: array, s?: array}
     */
    private function payload(Request $request): array
    {
        $json = [];
        $raw = $request->getContent();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return [
            'i' => $json['i'] ?? $request->input('i'),
            'n' => $json['n'] ?? $request->input('n'),
            'a' => $json['a'] ?? $request->input('a'),
            'g' => $json['g'] ?? $request->input('g', []),
            's' => $json['s'] ?? $request->input('s', []),
        ];
    }
}
