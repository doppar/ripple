<?php

namespace Doppar\Ripple;

use Doppar\Ripple\Console\CleanupTemporaryFilesCommand;
use Doppar\Ripple\Console\MakeRippleCommand;
use Doppar\Ripple\FileUpload\FileUploadService;
use Doppar\Ripple\Routing\RippleRouter;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Support\RippleViewController;
use Doppar\Ripple\Support\ViewCache;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\Launchers\PackageServiceLauncher;

class RippleLauncher extends PackageServiceLauncher
{
    /**
     * The package name used for config, views and publishing.
     *
     * @var string
     */
    protected string $packageName = 'ripple';

    /**
     * Configure the package name.
     *
     * @return void
     */
    protected function configurePackage(): void
    {
        $this->packageName = 'ripple';
    }

    /**
     * Register the Ripple config, router, view controller and core services.
     *
     * @return void
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfig(__DIR__ . '/../config/ripple.php', 'ripple');

        // view() resolves Controller and merges Ripple public props while a component renders.
        $this->app->bind(Controller::class, fn () => new RippleViewController());

        // Route::ripple() — replace Router before middleware/routes attach (shares static $routes)
        $existing = $this->app->router;
        if (! $existing instanceof RippleRouter) {
            $router = new RippleRouter($existing->getGateway());
            $this->app->instance('route', $router);
            $this->app->router = $router;
        }

        $this->app->singleton(StateSigner::class, fn () => StateSigner::fromConfig());
        $this->app->singleton(FileUploadService::class, fn () => new FileUploadService());
        $this->app->singleton(ComponentManager::class, function () {
            return new ComponentManager(signer: $this->app->get(StateSigner::class));
        });
    }

    /**
     * Boot Ripple routes, views, publishable assets and console commands.
     *
     * @return void
     */
    public function launch(): void
    {
        ViewCache::flush();

        $this->loadRoutes(__DIR__ . '/../routes/web.php');
        $this->loadViews(__DIR__ . '/../resources/views', 'ripple');

        $this->publishesConfig(__DIR__ . '/../config/ripple.php', 'ripple');

        $dist = $this->clientDistPath();
        if (is_file($dist)) {
            $this->publishes([
                $dist => public_path('vendor/ripple/ripple.min.js'),
            ], 'ripple-assets');
        }

        $this->commands([
            MakeRippleCommand::class,
            CleanupTemporaryFilesCommand::class,
        ]);

        $this->ensureClientAsset();
    }

    /**
     * Get the path to the bundled client script.
     *
     * @return string
     */
    private function clientDistPath(): string
    {
        return __DIR__ . '/../resources/dist/ripple.min.js';
    }

    /**
     * Copy the client script to the public directory when missing or outdated.
     *
     * @return void
     */
    private function ensureClientAsset(): void
    {
        $source = $this->clientDistPath();
        if (! is_file($source)) {
            return;
        }

        $target = public_path('vendor/ripple/ripple.min.js');
        $dir = dirname($target);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (! is_file($target) || filemtime($source) > filemtime($target)) {
            @copy($source, $target);
        }
    }
}
