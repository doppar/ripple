<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Attributes\Layout;
use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Attributes\Title;
use Doppar\Ripple\Delta\DiffEngine;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Doppar\Ripple\State\ComponentHydrator;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Support\Config;
use Doppar\Ripple\Testing\Testable;
use Doppar\Ripple\VDom\HtmlParser;
use Phaseolies\Http\Response;
use ReflectionClass;

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
            $lazy = $this->hydrator->lazyAttribute($class);
            if ($lazy === null) {
                throw new \LogicException("Component [{$class}] is marked lazy but has no #[Lazy] attribute.");
            }

            return $this->mountLazy($class, $params, $lazy);
        }

        $instance = $this->hydrator->mount($class, $params);
        $html = $this->renderHtml($instance);
        $this->parser->parse($html);
        $state = $instance->getState();
        $id = $instance->getId();
        $fingerprint = $this->signer->fingerprint($state);
        $signature = $this->signer->sign($state, $id);

        return $this->wrap($html, $id, $instance::class, $state, $fingerprint, $signature, $instance->getEventListeners());
    }

    /**
     * Render a full-page component inside a layout (Route::ripple).
     *
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function renderPage(string $component, array $params = []): Response
    {
        $class = $this->resolveClass($component);
        $html = $this->mount($class, $params);
        $layout = $this->resolveLayout($class);
        $title = $this->resolveTitle($class);

        return view('ripple::page', [
            'layout' => $layout,
            'title' => $title,
            'html' => $html,
        ]);
    }

    /**
     * @param array{
     *   i?: string, n?: string, a?: ?string, g?: array, s?: array,
     *   t?: string, p?: string, v?: mixed, f?: string, x?: string, er?: array, ev?: string, m?: array
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
        $verified = $this->verifySignature($state, $id, $payload);

        $instance = $this->hydrator->hydrate(
            $class,
            $state,
            $id !== '' ? $id : bin2hex(random_bytes(8)),
            allowLocked: $verified,
        );

        // Prior errors stay in the baseline so the live DOM (which still shows
        // them) diffs correctly — including #if error blocks. Re-diffing from a
        // cleared tree would INSERT those blocks again and stack duplicates.
        if (isset($payload['er']) && is_array($payload['er'])) {
            /** @var array<string, list<string>> $er */
            $er = $payload['er'];
            $instance->setErrorBag($er);
        }
        $flushedModels = false;
        if ($type !== 'u') {
            $flushedModels = $this->syncClientModels($instance, $payload['m'] ?? null);
        }

        $oldHtml = $this->renderHtml($instance);
        $oldTree = $this->parser->parse($oldHtml);

        // Snapshot first. Clearing before the baseline render makes the diff INSERT
        // error blocks the browser is still showing.
        if ($flushedModels) {
            $instance->setErrorBag([]);
        }

        try {
            if ($type === 'u') {
                $property = (string) ($payload['p'] ?? '');
                if ($property === '') {
                    throw new \InvalidArgumentException('Ripple property update requires p.');
                }
                $this->syncClientModels($instance, $payload['m'] ?? null, false);
                $instance->updateProperty($property, $payload['v'] ?? null);
            } else {
                $action = $payload['a'] ?? null;
                $args = is_array($payload['g'] ?? null) ? $payload['g'] : [];
                $event = $payload['ev'] ?? null;
                $instance->callAction(
                    is_string($action) ? $action : null,
                    $args,
                    is_string($event) && $event !== '' ? $event : null,
                );
            }
        } catch (ValidationException $e) {
            $instance->setErrorBag($e->errors());
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

        if ($instance->getUrlProperties() !== []) {
            $response['qs'] = $instance->getQueryString();
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
        $this->parser->parse($html);

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

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $params
     */
    public function test(string $component, array $params = []): Testable
    {
        return Testable::create($this, $this->resolveClass($component), $params);
    }

    /**
     * Render component HTML without the data-ripple wrapper (for testing / previews).
     */
    public function html(Component $instance): string
    {
        return $this->renderHtml($instance);
    }

    /**
     * @param class-string<Component>|string $component
     * @param array<string, mixed> $state
     */
    public function hydrateForTest(string $component, array $state, string $id): Component
    {
        return $this->hydrator->hydrate($this->resolveClass($component), $state, $id, allowLocked: true);
    }

    public function scripts(): string
    {
        $src = '/vendor/ripple/ripple.min.js';
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = function_exists('csrf_token') ? (string) (csrf_token() ?? '') : '';
        $csrfMeta = $csrf !== ''
            ? '<meta name="csrf-token" content="' . $h($csrf) . '">'
            : '';
        $debounce = (int) Config::get('ripple.performance.debounce_default', 150);

        return $csrfMeta
            . '<meta name="ripple-update" content="' . $h((string) Config::get('ripple.update_uri', '/ripple/update')) . '">'
            . '<meta name="ripple-lazy" content="' . $h((string) Config::get('ripple.lazy_uri', '/ripple/lazy')) . '">'
            . '<meta name="ripple-upload" content="' . $h((string) Config::get('ripple.upload_uri', '/ripple/upload')) . '">'
            . '<meta name="ripple-preview" content="' . $h((string) Config::get('ripple.preview_uri', '/ripple/preview')) . '">'
            . '<meta name="ripple-debounce" content="' . $h((string) max(0, $debounce)) . '">'
            . '<script src="' . $src . '" defer></script>';
    }

    public function signer(): StateSigner
    {
        return $this->signer;
    }

    /**
     * Apply pending DOM-bound values flushed by the client before an action.
     * Skips locked/hidden/computed props the same way updateProperty does.
     *
     * @param array<string, mixed>|mixed $models
     */
    private function syncClientModels(Component $instance, mixed $models, bool $resetValidation = true): bool
    {
        if (! is_array($models) || $models === []) {
            return false;
        }

        foreach ($models as $property => $value) {
            if (! is_string($property) || $property === '') {
                continue;
            }

            try {
                $instance->syncProperty($property, $value, $resetValidation);
            } catch (\InvalidArgumentException) {
                // Ignore unknown / locked / computed — client may over-report.
            }
        }

        return true;
    }

    /**
     * @param class-string<Component> $class
     */
    private function resolveLayout(string $class): string
    {
        $attrs = (new ReflectionClass($class))->getAttributes(Layout::class);
        if ($attrs !== []) {
            /** @var Layout $layout */
            $layout = $attrs[0]->newInstance();

            return $layout->name;
        }

        return (string) Config::get('ripple.layout', 'ripple::layouts.app');
    }

    /**
     * @param class-string<Component> $class
     */
    private function resolveTitle(string $class): string
    {
        $attrs = (new ReflectionClass($class))->getAttributes(Title::class);
        if ($attrs !== []) {
            /** @var Title $title */
            $title = $attrs[0]->newInstance();

            return $title->value;
        }

        $base = (new ReflectionClass($class))->getShortName();

        return preg_replace('/Component$/', '', $base) ?: $base;
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

        $namespace = rtrim((string) Config::get('ripple.component_namespace', 'App\\Ripple\\'), '\\');
        foreach ($this->candidateClassNames($component) as $path) {
            $namespaced = $namespace . '\\' . $path;
            if (class_exists($namespaced)) {
                /** @var class-string<Component> $namespaced */
                return $namespaced;
            }
        }

        throw new \InvalidArgumentException("Ripple component [{$component}] was not found.");
    }

    /**
     * @return list<string>
     */
    private function candidateClassNames(string $component): array
    {
        $path = str_replace(['/', '.'], '\\', trim($component, '/.'));
        $studly = implode('\\', array_map($this->studly(...), explode('\\', $path)));

        return $path === $studly ? [$path] : [$path, $studly];
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $params
     */
    private function mountLazy(string $class, array $params, Lazy $lazy): string
    {
        $id = bin2hex(random_bytes(8));
        $placeholder = $lazy->placeholder ?? '<div class="ripple-lazy-placeholder" style="padding:1rem;opacity:.6">Loading…</div>';
        $paramsJson = htmlspecialchars(json_encode($params, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<div data-ripple data-ripple-lazy data-ripple-id="%s" data-ripple-name="%s" data-ripple-params="%s">%s</div>',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $paramsJson,
            $placeholder,
        );
    }

    private function renderHtml(Component $instance): string
    {
        return Component::whileRendering($instance, function () use ($instance) {
            $out = $instance->render();

            if ($out instanceof \Phaseolies\Http\Response) {
                return $this->htmlFromViewResponse($instance, $out);
            }

            if (! is_string($out)) {
                return (string) $out;
            }

            // Odo view name (e.g. "ripple.components.counter") — no HTML tags
            if ($out !== '' && ! preg_match('/[<>]/', $out) && function_exists('view')) {
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
            $html,
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $payload
     * @return bool True when the payload signature was verified (Locked props may be trusted).
     */
    private function verifySignature(array $state, string $id, array $payload): bool
    {
        $mode = Config::get('ripple.security.verify_state', 'strict');
        $type = (string) ($payload['t'] ?? 'a');
        $shouldVerify = match ($mode) {
            'strict', true, 'true' => true,
            'property-updates' => $type === 'u',
            default => false,
        };

        if (! $shouldVerify) {
            return false;
        }

        $signature = (string) ($payload['x'] ?? '');
        if ($signature === '' || ! $this->signer->verify($state, $id, $signature)) {
            throw new \RuntimeException('Invalid Ripple state signature.');
        }

        return true;
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

        $payload = array_merge([
            'i' => $cid,
            'n' => $name,
            'd' => $deltas,
            's' => $state,
            'f' => $this->signer->fingerprint($state),
            'x' => $this->signer->sign($state, $cid),
        ], $extra);

        if (! array_key_exists('e', $payload)) {
            $payload['e'] = $instance->getErrorBag();
        }

        return $payload;
    }
}
