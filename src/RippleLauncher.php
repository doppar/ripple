<?php

namespace Doppar\Ripple;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Console\CleanupTemporaryFilesCommand;
use Doppar\Ripple\Console\MakeRippleCommand;
use Doppar\Ripple\FileUpload\FileUploadService;
use Doppar\Ripple\State\StateSigner;
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

        $this->app->singleton(StateSigner::class, fn () => StateSigner::fromConfig());
        $this->app->singleton(FileUploadService::class, fn () => new FileUploadService());
        $this->app->singleton(ComponentManager::class, function () {
            return new ComponentManager(signer: $this->app->get(StateSigner::class));
        });
    }

    public function launch(): void
    {
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

    private function clientDistPath(): string
    {
        return __DIR__ . '/../resources/dist/ripple.min.js';
    }

    private function ensureClientAsset(): void
    {
        $source = $this->clientDistPath();
        if (!is_file($source)) {
            return;
        }

        $target = public_path('vendor/ripple/ripple.min.js');
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_file($target) || filemtime($source) > filemtime($target)) {
            @copy($source, $target);
        }
    }
}
