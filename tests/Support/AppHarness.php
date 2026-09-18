<?php

namespace Doppar\Ripple\Tests\Support;

use Phaseolies\DI\Container;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\Request;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;

/**
 * Minimal container so package tests can call storage_path() / request() without a full Doppar app.
 */
final class AppHarness extends Container
{
    private string $base;

    public function __construct(string $base)
    {
        parent::__construct();
        $this->base = rtrim($base, DIRECTORY_SEPARATOR);
        @mkdir($this->base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app', 0775, true);
        @mkdir($this->base . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'config', 0775, true);
        self::setInstance($this);
        $this->bindTranslator();
    }

    public function basePath(string $path = ''): string
    {
        $path = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $path === '' ? $this->base : $this->base . DIRECTORY_SEPARATOR . $path;
    }

    public function storagePath(string $path = ''): string
    {
        $path = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = $this->base . DIRECTORY_SEPARATOR . 'storage';

        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function bindQuery(array $query): Request
    {
        $request = new Request();
        $request->query = new InputBag($query);
        $this->instance('request', $request);

        return $request;
    }

    public function destroy(): void
    {
        // Leave container instance alone if another harness replaced us.
        if (self::getInstance() === $this) {
            self::forgetInstance();
        }
        $this->rmTree($this->base);
    }

    private function bindTranslator(): void
    {
        $langPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'lang';
        $this->instance('translator', new Translator(new FileLoader($langPath), 'en'));
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
