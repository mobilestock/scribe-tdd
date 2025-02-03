<?php

namespace AjCastro\ScribeTdd;

use AjCastro\ScribeTdd\Commands\DeleteGeneratedFiles;
use AjCastro\ScribeTdd\Http\Middlewares\SetYamlContentTypeOnOpenApiRoutes;
use AjCastro\ScribeTdd\Tests\HttpExamples\HttpExampleCreatorMiddleware;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;

class ScribeTddServiceProvider extends ServiceProvider
{
    public function register()
    {
        App::bind(OpenAPISpecWriter::class, \AjCastro\ScribeTdd\Writing\OpenAPISpecWriter::class);

        $scribeMiddlewares = Config::get('scribe.laravel.middleware');
        $scribeMiddlewares[] = SetYamlContentTypeOnOpenApiRoutes::class;
        Config::set('scribe.laravel.middleware', $scribeMiddlewares);
    }

    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/scribe-tdd.php' => $this->app->configPath('scribe-tdd.php'),
            ],
            'scribe-tdd-config'
        );

        $this->mergeConfigFrom(__DIR__ . '/../config/scribe-tdd.php', 'scribe-tdd');

        if ($this->app->runningInConsole()) {
            $this->commands([DeleteGeneratedFiles::class]);
        }

        if ($this->app->environment('testing') && $this->app->runningInConsole() && config('scribe-tdd.enabled')) {
            $this->registerMiddleware();
        }
    }

    private function registerMiddleware(): void
    {
        // We need to register the middleware to web and api middlewareGroups
        // so that the route from $request->route() will be available.
        // In global middlewares, the route is not yet available.
        $this->app[HttpKernel::class]->appendMiddlewareToGroup('web', HttpExampleCreatorMiddleware::class);
        $this->app[HttpKernel::class]->appendMiddlewareToGroup('api', HttpExampleCreatorMiddleware::class);
    }
}
