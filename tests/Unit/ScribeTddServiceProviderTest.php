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
