<?php

namespace Doppar\Ripple;

use Doppar\Ripple\ComponentManager;
use Phaseolies\Launchers\PackageServiceLauncher;

class RippleLauncher extends PackageServiceLauncher
{
    protected string $packageName = 'ripple';

    protected function configurePackage(): void
    {
        $this->packageName = 'ripple';
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfig(__DIR__ . '/../config/ripple.php', 'ripple');

        $this->app->singleton(ComponentManager::class, function () {
            return new ComponentManager();
        });
    }

    public function launch(): void
    {
        $this->loadRoutes(__DIR__ . '/../routes/web.php');
        $this->loadViews(__DIR__ . '/../resources/views', 'ripple');

        $this->publishesConfig(__DIR__ . '/../config/ripple.php', 'ripple');

        $this->publishes([
            __DIR__ . '/../resources/js/ripple.js' => public_path('vendor/ripple/ripple.js'),
        ], 'ripple-assets');

        // Ensure the JS stub is available without a separate publish step in local path packages.
        $this->ensureClientAsset();
    }

    private function ensureClientAsset(): void
    {
        $target = public_path('vendor/ripple/ripple.js');
        $source = __DIR__ . '/../resources/js/ripple.js';

        if (!is_file($source)) {
            return;
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_file($target) || filemtime($source) > filemtime($target)) {
            @copy($source, $target);
        }
    }
}
