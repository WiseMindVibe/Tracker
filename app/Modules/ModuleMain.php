<?php

namespace App\Modules;

abstract class ModuleMain
{
    abstract public static function key(): string;

    abstract public function config(): ModuleConfig;
}
