<?php

namespace Doppar\Ripple\Console;

use Phaseolies\Console\Schedule\Command;

class MakeRippleCommand extends Command
{
    protected $name = 'make:ripple {name}';

    protected $description = 'Create a new Ripple component class';

    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $namespace = 'App\\Ripple' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('src/Ripple', $name);

            if (file_exists($filePath)) {
                $this->displayError('Ripple component already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');

                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            file_put_contents($filePath, $this->stub($namespace, $className));
            $this->displaySuccess('Ripple component created:');
            $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');

            return Command::SUCCESS;
        });
    }

    private function stub(string $namespace, string $className): string
    {
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

    public function render(): string
    {
        return '<div><p>' . \$this->message . '</p><button type="button" ripple:call="refresh">Refresh</button></div>';
    }
}

PHP;
    }
}
