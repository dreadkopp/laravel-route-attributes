<?php

namespace Spatie\RouteAttributes;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class RouteAttributesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/route-attributes.php' => config_path('route-attributes.php'),
            ], 'config');
        }

        $this->registerRoutes();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/route-attributes.php', 'route-attributes');

        $this->app->bind(RouteRegistrar::class,fn()=> new RouteRegistrar(app()->router));
    }

    protected function registerRoutes(): void
    {
        if (! $this->shouldRegisterRoutes()) {
            return;
        }

        $routeRegistrar = app(RouteRegistrar::class)
            ->useMiddleware(config('route-attributes.middleware') ?? []);

        collect($this->getRouteDirectories())->each(function (string|array $directory, string|int $namespace) use ($routeRegistrar) {
            if (is_array($directory)) {
                $options = Arr::except($directory, ['namespace', 'base_path']);

                $routeRegistrar
                    ->useRootNamespace($directory['namespace'] ?? app()->getNamespace())
                    ->useBasePath($directory['base_path'] ?? (isset($directory['namespace']) ? $namespace : app()->path()))
                    ->group($options, fn () => $routeRegistrar->registerDirectory($namespace));
            } else {
                is_string($namespace)
                    ? $routeRegistrar
                        ->useRootNamespace($namespace)
                        ->useBasePath($directory)
                        ->registerDirectory($directory)
                    : $routeRegistrar
                        ->useRootNamespace(app()->getNamespace())
                        ->useBasePath(app()->path())
                        ->registerDirectory($directory);
            }
        });
    }

    protected function shouldRegisterRoutes(): bool
    {
        if (! config('route-attributes.enabled')) {
            return false;
        }

        if ($this->app->routesAreCached()) {
            return false;
        }

        return true;
    }

    protected function getRouteDirectories(): array
    {
        return array_reverse(config('route-attributes.directories'));
    }
}
