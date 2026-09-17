<?php

namespace Doppar\Ripple\Http;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
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

            return new JsonResponse($result, ($result['ok'] ?? true) ? 200 : 422);
        } catch (RedirectException $e) {
            return new JsonResponse([
                'ok' => true,
                'rd' => $e->redirect(),
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'ok' => false,
                'e' => $e->errors(),
                'message' => $e->getMessage(),
            ], 422);
        }
        // Missing action/component and other failures bubble to Doppar's ErrorHandler
        // (debug page when APP_DEBUG, production 500 page otherwise).
    }

    public function lazy(Request $request): JsonResponse
    {
        $json = $this->jsonBody($request);
        $name = (string) ($json['n'] ?? $request->input('n') ?? '');
        $params = $json['g'] ?? $request->input('g', []);
        $id = (string) ($json['i'] ?? $request->input('i') ?? '');

        return new JsonResponse($this->manager->loadLazy(
            $name,
            is_array($params) ? $params : [],
            $id !== '' ? $id : null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $json = $this->jsonBody($request);

        return [
            'i' => $json['i'] ?? $request->input('i'),
            'n' => $json['n'] ?? $request->input('n'),
            'a' => $json['a'] ?? $request->input('a'),
            'g' => $json['g'] ?? $request->input('g', []),
            's' => $json['s'] ?? $request->input('s', []),
            't' => $json['t'] ?? $request->input('t', 'a'),
            'p' => $json['p'] ?? $request->input('p'),
            'v' => $json['v'] ?? $request->input('v'),
            'f' => $json['f'] ?? $request->input('f'),
            'x' => $json['x'] ?? $request->input('x'),
            'er' => $json['er'] ?? $request->input('er'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        $raw = $request->getContent();
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
