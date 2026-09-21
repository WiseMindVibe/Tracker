<?php

namespace App\Console\Commands;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleCache extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Discover modules and cache the registry';

    public function handle(ModuleRegistry $registry): int
    {
        $modules = $registry->rebuild();

        $this->info('Modules cached: ' . implode(', ', array_keys($modules)));

        return self::SUCCESS;
    }
}
