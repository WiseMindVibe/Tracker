<?php

namespace App\Modules;

use App\Modules\Exceptions\ModuleNotFoundException;
use Symfony\Component\Finder\Finder;

class ModuleRegistry
{
    protected array $modules = [];

    protected string $cachePath;

    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath;
    }

    /**
     * Populate the registry, using the cache file if it exists,
     * otherwise scanning the filesystem.
     */
    public function discover(): void
    {
        if ($this->loadFromCache()) {
            return;
        }

        $this->scan();
    }

    /**
     * Force a filesystem scan and rebuild the cache file.
     * Called by `php artisan module:cache`.
     */
    public function rebuild(): array
    {
        $this->modules = [];
        $this->scan();
        $this->writeCache();

        return $this->modules;
    }

    public function clearCache(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    protected function loadFromCache(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }

        $this->modules = require $this->cachePath;

        return true;
    }

    protected function writeCache(): void
    {
        $export = var_export($this->modules, true);

        file_put_contents(
            $this->cachePath,
            "<?php\n\nreturn {$export};\n"
        );
    }

    protected function scan(): void
    {
        $path = app_path('Modules');

        if (!is_dir($path)) {
            return;
        }

        $finder = (new Finder())
            ->files()
            ->in($path)
            ->name('*Module.php');

        foreach ($finder as $file) {
            $class = $this->classFromPath($file->getRealPath());

            if (!$class || !class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, ModuleMain::class)) {
                continue;
            }

            $this->register($class::key(), $class);
        }
    }

    /**
     * Derive the fully qualified class name directly from the file path,
     * relying on PSR-4 rather than parsing file contents.
     */
    protected function classFromPath(string $path): ?string
    {
        $base = app_path('Modules');

        $relative = str_replace($base . DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace('.php', '', $relative);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return 'App\\Modules\\' . $relative;
    }

    public function register(string $key, string $module): void
    {
        $this->modules[$key] = $module;
    }

    public function get(string $key): string
    {
        return $this->modules[$key]
            ?? throw new ModuleNotFoundException($key);
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function all(): array
    {
        return $this->modules;
    }
}
