<?php

namespace App\Providers;

use App\Modules\ModuleManager;
use App\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function ($app) {
            $registry = new ModuleRegistry(
                $app->bootstrapPath('cache/modules.php')
            );

            $registry->discover();

            return $registry;
        });

        $this->app->singleton(ModuleManager::class);
    }
}
