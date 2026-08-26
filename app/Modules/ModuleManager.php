<?php

namespace App\Modules;

class ModuleManager
{
    public function __construct(
        protected ModuleRegistry $registry
    ) {
    }

    public function get(string $key): ModuleMain
    {
        $class = $this->registry->get($key);

        return app($class);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    public function all(): array
    {
        return array_map(
            fn (string $class) => app($class),
            $this->registry->all()
        );
    }
}
