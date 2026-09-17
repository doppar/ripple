<?php

namespace Doppar\Ripple\Console;

use Phaseolies\Console\Schedule\Command;

class MakeRippleCommand extends Command
{
    protected $name = 'make:ripple {name} {--inline : Keep markup in the PHP class instead of an Odo view}';

    protected $description = 'Create a new Ripple component (class + Odo view by default)';

    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $namespace = 'App\\Ripple' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('src/Ripple', $name);
            $inline = (bool) $this->option('inline');
            $viewName = $this->viewName($parts, $className);
            $viewPath = $this->viewPath($parts, $className);

            if (file_exists($filePath)) {
                $this->displayError('Ripple component already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');

                return Command::FAILURE;
            }

            if (!$inline && file_exists($viewPath)) {
                $this->displayError('Ripple view already exists at:');
                $this->line('<fg=white>' . $this->relativePath($viewPath) . '</>');

                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            file_put_contents($filePath, $this->classStub($namespace, $className, $inline, $viewName));
            $this->displaySuccess('Ripple component created:');
            $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');

            if (!$inline) {
                $viewDir = dirname($viewPath);
                if (!is_dir($viewDir)) {
                    mkdir($viewDir, 0755, true);
                }
                file_put_contents($viewPath, $this->viewStub($className));
                $this->line('<fg=white>' . $this->relativePath($viewPath) . '</>');
            }

            return Command::SUCCESS;
        });
    }

    /**
     * @param list<string> $parts
     */
    private function viewName(array $parts, string $className): string
    {
        $segments = array_map([$this, 'toKebab'], [...$parts, $className]);

        return 'ripple.' . implode('.', $segments);
    }

    /**
     * @param list<string> $parts
     */
    private function viewPath(array $parts, string $className): string
    {
        $segments = array_map([$this, 'toKebab'], [...$parts, $className]);
        $relative = 'templates/views/ripple/' . implode('/', $segments) . '.odo.php';

        return base_path($relative);
    }

    private function toKebab(string $value): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return strtolower($kebab);
    }

    private function classStub(string $namespace, string $className, bool $inline, string $viewName): string
    {
        if ($inline) {
            return <<<PHP
<?php

namespace {$namespace};

use Doppar\\Ripple\\Attributes\\Invokable;
use Doppar\\Ripple\\Component;

class {$className} extends Component
{
    public string \$message = 'Hello from Ripple';

    #[Invokable]
    public function refresh(): void
    {
        //
    }

    public function render(): mixed
    {
        return '<div><p>' . \$this->message . '</p><button type="button" ripple:call="refresh">Refresh</button></div>';
    }
}

PHP;
        }

        return <<<PHP
<?php

namespace {$namespace};

use Doppar\\Ripple\\Attributes\\Invokable;
use Doppar\\Ripple\\Component;

class {$className} extends Component
{
    public string \$message = 'Hello from Ripple';

    #[Invokable]
    public function refresh(): void
    {
        //
    }

    public function render(): mixed
    {
        return view('{$viewName}');
    }
}

PHP;
    }

    private function viewStub(string $className): string
    {
        return <<<ODO
<div>
    <p>[[ \$message ]]</p>
    <button type="button" ripple:call="refresh">Refresh</button>
</div>

ODO;
    }
}
