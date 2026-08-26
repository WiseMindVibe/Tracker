<?php

namespace App\Modules\ModuleList\AffiliateCatalog;

use App\Modules\ModuleMain;
use App\Modules\ModuleConfig;

class Module extends ModuleMain
{
    protected Config $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    public static function key(): string
    {
        return 'affiliateCatalog';
    }

    public function config(): ModuleConfig
    {
        return $this->config;
    }
}
