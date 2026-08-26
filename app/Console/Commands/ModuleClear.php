<?php

namespace App\Console\Commands;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleClear extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Clear the cached module registry';

    public function handle(ModuleRegistry $registry): int
    {
        $registry->clearCache();

        $this->info('Module cache cleared.');

        return self::SUCCESS;
    }
}
