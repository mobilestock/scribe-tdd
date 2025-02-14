<?php

namespace AjCastro\ScribeTdd;

use AjCastro\ScribeTdd\Commands\DeleteGeneratedFiles;
use AjCastro\ScribeTdd\Http\Middlewares\SetYamlContentTypeOnOpenApiRoutes;
use AjCastro\ScribeTdd\Tests\HttpExamples\HttpExampleCreatorMiddleware;
use AjCastro\ScribeTdd\Writing\OpenAPISpecWriter as OpenAPISpecWriterScribeTdd;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
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

        Config::set('scribe.laravel.add_routes', true);
        Config::set('scribe.laravel.docs_url', '/docs');

        Config::set('scribe.auth.enabled', true);
        Config::set('scribe.auth.placeholder', '{YOUR_AUTH_KEY}');
        Config::set('scribe.auth.in', 'bearer');
        Config::set('scribe.example_languages', ['php', 'bash', 'javascript']);
        Config::set('scribe.strategies', [
            'metadata' => [\AjCastro\ScribeTdd\Strategies\Metadata\GetFromRoute::class],
            'urlParameters' => [\AjCastro\ScribeTdd\Strategies\UrlParameters\GetFromUrlParamTagFromScribeTdd::class],
            'queryParameters' => [
                \AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromTestResult::class,
            ],
            'headers' => [\AjCastro\ScribeTdd\Strategies\Headers\GetFromHeaderTagFromScribeTdd::class],
            'bodyParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator::class,
                \AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromTestResult::class,
                \AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromBodyParamTagFromScribeTdd::class,
            ],
            'responses' => [
                \AjCastro\ScribeTdd\Strategies\Responses\GetFromTestResult::class,
                \AjCastro\ScribeTdd\Strategies\Responses\UseResponseTagFromScribeTdd::class,
                \AjCastro\ScribeTdd\Strategies\Responses\UseResponseFileTagFromScribeTdd::class,
            ],
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
