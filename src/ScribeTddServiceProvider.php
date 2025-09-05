<?php

namespace AjCastro\ScribeTdd;

use AjCastro\ScribeTdd\Commands\DeleteGeneratedFiles;
use AjCastro\ScribeTdd\Tests\HttpExamples\HttpExampleCreatorMiddleware;
use AjCastro\ScribeTdd\Writing\OpenAPISpecWriter as OpenAPISpecWriterScribeTdd;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;

class ScribeTddServiceProvider extends ServiceProvider
{
    public function register()
    {
        App::bind(OpenAPISpecWriter::class, OpenAPISpecWriterScribeTdd::class);

        $excludeRoutes = Config::get('scribe.routes.0.exclude');
        $excludeRoutes[] = '_ignition/*';
        $excludeRoutes[] = 'oauth/*';

        Config::set('scribe.routes.0.match.prefixes', ['*']);
        Config::set('scribe.routes.0.match.domains', ['*']);
        Config::set('scribe.routes.0.exclude', $excludeRoutes);
        Config::set('scribe.type', 'external_static');
        Config::set('scribe.theme', 'elements');
        Config::set('scribe.auth.enabled', true);
        Config::set('scribe.auth.in', 'bearer');

        Config::set('scribe.strategies', [
            'metadata' => [Strategies\Metadata\GetFromRoute::class],
            'urlParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamAttribute::class,
                Strategies\UrlParameters\GetFromUrlParamTagFromScribeTdd::class,
            ],
            'queryParameters' => [
                Strategies\QueryParameters\GetFromInlineValidator::class,
                \AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromTestResult::class,
            ],
            'headers' => [
                Strategies\Headers\GetFromHeaderTagFromScribeTdd::class,
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderAttribute::class,
            ],
            'bodyParameters' => [Strategies\BodyParameters\GetFromInlineValidator::class],
            'responses' => [Strategies\Responses\GetFromTestResult::class],
            'responseFields' => [],
        ]);
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

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([DeleteGeneratedFiles::class]);
        if (!$this->app->environment('testing') || !config('scribe-tdd.enabled')) {
            return;
        }

        $this->registerMiddleware();
        if (empty($_SERVER['IN_PARALLEL'])) {
            return;
        }

        ParallelTesting::tearDownProcess(function () {
            Artisan::call('scribe:generate');
        });
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
