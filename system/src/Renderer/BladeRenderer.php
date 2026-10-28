<?php

declare(strict_types=1);

namespace GSpot\Renderer;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

class BladeRenderer
{
    private Factory $factory;
    private string $cachePath;

    public function __construct(array $viewPaths, string $cachePath)
    {
        $this->cachePath = $cachePath;

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $this->factory = $this->createFactory($viewPaths, $cachePath);
    }

    public function render(string $view, array $data = []): string
    {
        if (!$this->factory->exists($view)) {
            if ($this->factory->exists('errors.404')) {
                return $this->factory->make('errors.404', $data)->render();
            }
            return '<!DOCTYPE html><html><body><h1>404 — Not Found</h1></body></html>';
        }

        return $this->factory->make($view, $data)->render();
    }

    public function exists(string $view): bool
    {
        return $this->factory->exists($view);
    }

    public function addViewPath(string $path): void
    {
        $this->factory->getFinder()->addLocation($path);
    }

    public function share(string $key, mixed $value): void
    {
        $this->factory->share($key, $value);
    }

    public function factory(): Factory
    {
        return $this->factory;
    }

    private function createFactory(array $viewPaths, string $cachePath): Factory
    {
        $container = new Container();
        $filesystem = new Filesystem();
        $dispatcher = new Dispatcher($container);

        $container->instance('files', $filesystem);
        $container->instance('events', $dispatcher);

        $resolver = new EngineResolver();

        $resolver->register('php', function () {
            return new PhpEngine();
        });

        $bladeCompiler = new BladeCompiler($filesystem, $cachePath);

        $resolver->register('blade', function () use ($bladeCompiler) {
            return new CompilerEngine($bladeCompiler);
        });

        $container->instance('blade.compiler', $bladeCompiler);

        $finder = new FileViewFinder($filesystem, $viewPaths);

        $factory = new Factory($resolver, $finder, $dispatcher);
        $factory->setContainer($container);

        Container::setInstance($container);

        return $factory;
    }
}
