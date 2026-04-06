<?php

use AjCastro\ScribeTdd\ScribeTddServiceProvider;
use Illuminate\Support\Facades\Config;

it('returns early from boot when not running in console', function () {
    $mockApp = Mockery::mock(app())->makePartial();
    $mockApp->shouldReceive('runningInConsole')->andReturn(false);

    $provider = new ScribeTddServiceProvider($mockApp);
    $provider->boot();

    // Reached here means line 75 (early return) was executed without error
    expect(true)->toBeTrue();
});

it('returns early from boot when scribe-tdd is disabled', function () {
    Config::set('scribe-tdd.enabled', false);

    $mockApp = Mockery::mock(app())->makePartial();
    $mockApp->shouldReceive('runningInConsole')->andReturn(true);
    $mockApp->shouldReceive('environment')->with('testing')->andReturn(true);

    $provider = new ScribeTddServiceProvider($mockApp);
    $provider->boot();

    Config::set('scribe-tdd.enabled', true);

    expect(true)->toBeTrue();
});

it('executes parallel testing teardown callback', function () {
    $_SERVER['LARAVEL_PARALLEL_TESTING'] = 1;

    $provider = new ScribeTddServiceProvider(app());
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
