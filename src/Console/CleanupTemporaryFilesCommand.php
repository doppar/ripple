<?php

namespace Doppar\Ripple\Console;

use Doppar\Ripple\FileUpload\FileUploadService;
use Phaseolies\Console\Schedule\Command;

class CleanupTemporaryFilesCommand extends Command
{
    protected $name = 'ripple:cleanup';

    protected $description = 'Delete expired Ripple temporary uploads';

    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $deleted = (new FileUploadService())->cleanupOldFiles();
            $this->displaySuccess("Deleted {$deleted} temporary upload(s).");

            return Command::SUCCESS;
        });
    }
}
