<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Delta\DiffEngine;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Doppar\Ripple\State\ComponentHydrator;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Support\Config;
use Doppar\Ripple\VDom\HtmlParser;

final class ComponentManager
{
    /** @var array<string, class-string<Component>> */
    private array $aliases = [];

    private ComponentHydrator $hydrator;
    private StateSigner $signer;

    public function __construct(
        private readonly HtmlParser $parser = new HtmlParser(),
        private readonly DiffEngine $diff = new DiffEngine(),
        ?StateSigner $signer = null,
        ?ComponentHydrator $hydrator = null,
    ) {
        $this->signer = $signer ?? StateSigner::fromConfig();
        $this->hydrator = $hydrator ?? new ComponentHydrator($this->signer);
    }

    /**
     * @param class-string<Component> $class
     */
    public function register(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function mount(string $component, array $params = []): string
    {
        $class = $this->resolveClass($component);

        if ($this->hydrator->isLazy($class)) {
            return $this->mountLazy($class, $params, $this->hydrator->lazyAttribute($class));
        }

        $instance = $this->hydrator->mount($class, $params);
        $html = $this->renderHtml($instance);
        $state = $instance->getState();
        $id = $instance->getId();
        $fingerprint = $this->signer->fingerprint($state);
        $signature = $this->signer->sign($state, $id);

        return $this->wrap($html, $id, $instance::class, $state, $fingerprint, $signature, $instance->getEventListeners());
    }

    /**
     * @param array{
     *   i?: string, n?: string, a?: ?string, g?: array, s?: array,
     *   t?: string, p?: string, v?: mixed, f?: string, x?: string, er?: array
     * } $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $name = (string) ($payload['n'] ?? '');
        $id = (string) ($payload['i'] ?? '');
        $state = is_array($payload['s'] ?? null) ? $payload['s'] : [];
        $type = (string) ($payload['t'] ?? 'a');

        if ($name === '') {
            throw new \InvalidArgumentException('Ripple update requires a component name.');
        }

        $class = $this->resolveClass($name);
        $this->verifySignature($state, $id, $payload);

        $instance = $this->hydrator->hydrate($class, $state, $id !== '' ? $id : bin2hex(random_bytes(8)));
        if (isset($payload['er']) && is_array($payload['er'])) {
            /** @var array<string, list<string>> $er */
            $er = $payload['er'];
            $instance->setErrorBag($er);
        }

        $oldHtml = $this->renderHtml($instance);
        $oldTree = $this->parser->parse($oldHtml);

        try {
            if ($type === 'u') {
                $property = (string) ($payload['p'] ?? '');
                if ($property === '') {
                    throw new \InvalidArgumentException('Ripple property update requires p.');
                }
                $instance->updateProperty($property, $payload['v'] ?? null);
            } else {
                $action = $payload['a'] ?? null;
                $args = is_array($payload['g'] ?? null) ? $payload['g'] : [];
                $instance->callAction(is_string($action) ? $action : null, $args);
            }
        } catch (ValidationException $e) {
            $newState = $instance->getState();
            $newHtml = $this->renderHtml($instance);
            $patch = $this->diff->diff($oldTree, $this->parser->parse($newHtml));

            return $this->successPayload($instance, $id, $name, $patch->toArray(), $newState, [
                'ok' => false,
                'e' => $e->errors(),
            ]);
        } catch (RedirectException $e) {
            return [
                'ok' => true,
                'i' => $id,
                'n' => $name,
                'rd' => $e->redirect(),
                's' => $instance->getState(),
                'd' => [],
            ];
        }

        $instance->dehydrateForResponse();
        $newState = $instance->getState();
        $newHtml = $this->renderHtml($instance);
        $patch = $this->diff->diff($oldTree, $this->parser->parse($newHtml));

        $response = $this->successPayload($instance, $id, $name, $patch->toArray(), $newState, ['ok' => true]);

        $events = $instance->getDispatchedEvents();
        $browser = $instance->getBrowserEvents();
        if ($events !== []) {
            $response['ev'] = $events;
        }
        if ($browser !== []) {
            $response['be'] = $browser;
        }
        $instance->clearDispatchedEvents();

        $qs = $instance->getQueryString();
        if ($qs !== []) {
            $response['qs'] = $qs;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function loadLazy(string $component, array $params = [], ?string $id = null): array
    {
        $class = $this->resolveClass($component);
        $instance = $this->hydrator->mount($class, $params, $id);
        $state = $instance->getState();
        $cid = $instance->getId();
        $html = $this->renderHtml($instance);

        return [
            'ok' => true,
            'i' => $cid,
            'n' => $instance::class,
            'h' => $html,
            's' => $state,
            'f' => $this->signer->fingerprint($state),
            'x' => $this->signer->sign($state, $cid),
            'el' => $instance->getEventListeners(),
        ];
    }

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function make(string $component, array $params = []): Component
    {
        $class = $this->resolveClass($component);

        return $this->hydrator->mount($class, $params);
    }

    public function scripts(): string
    {
        $src = '/vendor/ripple/ripple.min.js';
        $uri = htmlspecialchars((string) Config::get('ripple.update_uri', '/ripple/update'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = function_exists('csrf_token') ? (string) (csrf_token() ?? '') : '';
        $csrfMeta = $csrf !== ''
            ? '<meta name="csrf-token" content="' . htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            : '';

        return $csrfMeta
            . '<meta name="ripple-update" content="' . $uri . '">'
            . '<meta name="ripple-lazy" content="' . htmlspecialchars((string) Config::get('ripple.lazy_uri', '/ripple/lazy'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<meta name="ripple-upload" content="' . htmlspecialchars((string) Config::get('ripple.upload_uri', '/ripple/upload'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<script src="' . $src . '" defer></script>';
    }

    public function signer(): StateSigner
    {
        return $this->signer;
    }

    /**
     * @return class-string<Component>
     */
    private function resolveClass(string $component): string
    {
        if (isset($this->aliases[$component])) {
            return $this->aliases[$component];
        }

        if (class_exists($component)) {
            /** @var class-string<Component> $component */
            return $component;
        }

        $path = str_replace('/', '\\', $component);
        $namespaced = rtrim((string) Config::get('ripple.component_namespace', 'App\\Ripple\\'), '\\') . '\\' . $path;
        if (class_exists($namespaced)) {
            /** @var class-string<Component> $namespaced */
            return $namespaced;
        }

        throw new \InvalidArgumentException("Ripple component [{$component}] was not found.");
    }

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $params
     */
    private function mountLazy(string $class, array $params, ?Lazy $lazy): string
    {
        $id = bin2hex(random_bytes(8));
        $placeholder = $lazy?->placeholder ?? '<div class="ripple-lazy-placeholder" style="padding:1rem;opacity:.6">Loading…</div>';
        $paramsJson = htmlspecialchars(json_encode($params, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<div data-ripple data-ripple-lazy data-ripple-id="%s" data-ripple-name="%s" data-ripple-params="%s">%s</div>',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $paramsJson,
            $placeholder
        );
    }

    private function renderHtml(Component $instance): string
    {
        return Component::whileRendering($instance, function () use ($instance) {
            $out = $instance->render();

            if ($out instanceof \Phaseolies\Http\Response) {
                return $this->htmlFromViewResponse($instance, $out);
            }

            if (!is_string($out)) {
                return (string) $out;
            }

            // Odo view name (e.g. "ripple.components.counter") — no HTML tags
            if ($out !== '' && !preg_match('/[<>]/', $out) && function_exists('view')) {
                $response = view($out, $instance->viewData());

                return (string) ($response->getBody() ?? '');
            }

            return $out;
        });
    }

    private function htmlFromViewResponse(Component $instance, \Phaseolies\Http\Response $response): string
    {
        // Body already includes public props when rendered under whileRendering()
        $body = $response->getBody();
        if (is_string($body) && $body !== '') {
            return $body;
        }

        $original = $response->getOriginal();
        if (is_array($original) && isset($original['view']) && is_string($original['view']) && function_exists('view')) {
            $extra = is_array($original['data'] ?? null) ? $original['data'] : [];
            $fresh = view($original['view'], array_merge($instance->viewData(), $extra));

            return (string) ($fresh->getBody() ?? '');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, list<string>> $eventListeners
     */
    private function wrap(
        string $html,
        string $id,
        string $name,
        array $state,
        string $fingerprint,
        string $signature,
        array $eventListeners,
    ): string {
        return sprintf(
            '<div data-ripple data-ripple-id="%s" data-ripple-name="%s" data-ripple-state="%s" data-ripple-f="%s" data-ripple-x="%s" data-ripple-el="%s">%s</div>',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars(json_encode($state, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($fingerprint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($signature, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars(json_encode($eventListeners, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $html
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $payload
     */
    private function verifySignature(array $state, string $id, array $payload): void
    {
        $mode = Config::get('ripple.security.verify_state', 'property-updates');
        $type = (string) ($payload['t'] ?? 'a');
        $shouldVerify = match ($mode) {
            'strict', true, 'true' => true,
            'property-updates' => $type === 'u',
            default => false,
        };

        if (!$shouldVerify) {
            return;
        }

        $signature = (string) ($payload['x'] ?? '');
        if ($signature === '' || !$this->signer->verify($state, $id, $signature)) {
            throw new \RuntimeException('Invalid Ripple state signature.');
        }
    }

    /**
     * @param list<array<string, mixed>> $deltas
     * @param array<string, mixed> $state
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function successPayload(
        Component $instance,
        string $id,
        string $name,
        array $deltas,
        array $state,
        array $extra = [],
    ): array {
        $cid = $instance->getId() ?: $id;

        return array_merge([
            'i' => $cid,
            'n' => $name,
            'd' => $deltas,
            's' => $state,
            'f' => $this->signer->fingerprint($state),
            'x' => $this->signer->sign($state, $cid),
        ], $extra);
    }
}
