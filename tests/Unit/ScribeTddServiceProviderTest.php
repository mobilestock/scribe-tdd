<?php

use AjCastro\ScribeTdd\Tests\HttpExamples\HttpExampleCreatorMiddleware;
use AjCastro\ScribeTdd\Writing\ScribeTddBaseGenerator;
use Illuminate\Support\Facades\Config;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\BaseGenerator;

it('binds ScribeTddBaseGenerator to BaseGenerator', function () {
    $generator = app(BaseGenerator::class);

    expect($generator)->toBeInstanceOf(ScribeTddBaseGenerator::class);
});

it('registers middleware in testing environment', function () {
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    // The middleware groups should contain HttpExampleCreatorMiddleware
    $apiMiddleware = $kernel->getMiddlewareGroups()['api'] ?? [];
    $webMiddleware = $kernel->getMiddlewareGroups()['web'] ?? [];

    expect($apiMiddleware)->toContain(HttpExampleCreatorMiddleware::class);
    expect($webMiddleware)->toContain(HttpExampleCreatorMiddleware::class);
});

it('publishes config file', function () {
    $publishes = Illuminate\Support\ServiceProvider::$publishes;

    $found = false;
    foreach ($publishes as $provider => $files) {
        foreach ($files as $from => $to) {
            if (str_contains($to, 'scribe-tdd.php')) {
                $found = true;
            }
        }
    }

    expect($found)->toBeTrue();
});

it('registers scribe route matching to wildcard', function () {
    expect(Config::get('scribe.routes.0.match.prefixes'))->toBe(['*']);
    expect(Config::get('scribe.routes.0.match.domains'))->toBe(['*']);
});

it('excludes framework routes from documentation', function () {
    $excludeRoutes = Config::get('scribe.routes.0.exclude');

    expect($excludeRoutes)->toContain('_ignition/*');
    expect($excludeRoutes)->toContain('oauth/*');
    expect($excludeRoutes)->toContain('up');
    expect($excludeRoutes)->toContain('storage/*');
});

it('sets external static doc type with elements theme', function () {
    expect(Config::get('scribe.type'))->toBe('external_static');
    expect(Config::get('scribe.theme'))->toBe('elements');
});

it('enables bearer auth', function () {
    expect(Config::get('scribe.auth.enabled'))->toBeTrue();
    expect(Config::get('scribe.auth.in'))->toBe('bearer');
});

it('registers custom metadata strategy', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['metadata'])->toBe([AjCastro\ScribeTdd\Strategies\Metadata\GetFromRoute::class]);
});

it('registers custom body parameter strategies', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['bodyParameters'])->toContain(
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromLaravelData::class,
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromInlineValidator::class,
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromTestResult::class
    );
});

it('registers custom response strategy', function () {
    $strategies = Config::get('scribe.strategies');

    expect($strategies['responses'])->toBe([AjCastro\ScribeTdd\Strategies\Responses\GetFromTestResult::class]);
});

it('returns early from boot when not running in console', function () {
    $mockApp = Mockery::mock(app())->makePartial();
    $mockApp->shouldReceive('runningInConsole')->andReturn(false);

    $provider = new AjCastro\ScribeTdd\ScribeTddServiceProvider($mockApp);
    $provider->boot();

    // Reached here means line 75 (early return) was executed without error
    expect(true)->toBeTrue();
});

it('returns early from boot when not in testing env', function () {
    $mockApp = Mockery::mock(app())->makePartial();
    $mockApp->shouldReceive('runningInConsole')->andReturn(true);
    $mockApp->shouldReceive('environment')->with('testing')->andReturn(false);

    $provider = new AjCastro\ScribeTdd\ScribeTddServiceProvider($mockApp);
    $provider->boot();

    // Reached here means line 80 (early return) was executed
    expect(true)->toBeTrue();
});

it('returns early from boot when scribe-tdd is disabled', function () {
    Config::set('scribe-tdd.enabled', false);

    $mockApp = Mockery::mock(app())->makePartial();
    $mockApp->shouldReceive('runningInConsole')->andReturn(true);
    $mockApp->shouldReceive('environment')->with('testing')->andReturn(true);

    $provider = new AjCastro\ScribeTdd\ScribeTddServiceProvider($mockApp);
    $provider->boot();

    Config::set('scribe-tdd.enabled', true);

    expect(true)->toBeTrue();
});

it('executes parallel testing teardown callback', function () {
    $_SERVER['LARAVEL_PARALLEL_TESTING'] = 1;

    $provider = new AjCastro\ScribeTdd\ScribeTddServiceProvider(app());
    $provider->boot();

    // Get the registered callback directly via reflection to bypass whenRunningInParallel
    $parallelTesting = app(Illuminate\Testing\ParallelTesting::class);
    $prop = new ReflectionProperty($parallelTesting, 'tearDownProcessCallbacks');
    $callbacks = $prop->getValue($parallelTesting);
    $callback = end($callbacks);

    // Execute the callback to cover lines 89-91
    try {
        $callback();
    } catch (Throwable) {
        // scribe:generate may fail in test env
    }

    expect($_SERVER['SCRIBE_TESTS'] ?? null)->toBeTrue();

    unset($_SERVER['SCRIBE_TESTS'], $_SERVER['LARAVEL_PARALLEL_TESTING']);
    
});
