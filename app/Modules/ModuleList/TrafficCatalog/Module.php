<?php

namespace App\Modules\ModuleList\TrafficCatalog;

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
        return 'trafficCatalog';
    }

    public function config(): ModuleConfig
    {
        return $this->config;
    }
}
