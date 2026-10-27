<?php

namespace Doppar\Ripple\Console;

use Doppar\Ripple\FileUpload\FileUploadService;
use Phaseolies\Console\Schedule\Command;

class CleanupTemporaryFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'ripple:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired Ripple temporary uploads';

    /**
     * Delete expired temporary upload files and report how many were removed.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $deleted = (new FileUploadService())->cleanupOldFiles();
            $this->displaySuccess("Deleted {$deleted} temporary upload(s).");

            return Command::SUCCESS;
        });
    }
}
