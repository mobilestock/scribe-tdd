<?php

use AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromLaravelData;

it('includes data class with query parameters docblock', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');

    expect($reflection->invoke($strategy, $mockClass))->toBeTrue();
});

it('includes data class with queryParameters method', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn(false);
    $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);

    expect($reflection->invoke($strategy, $mockClass))->toBeTrue();
});

it('excludes data class without query indicators', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn(false);
    $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(false);

    expect($reflection->invoke($strategy, $mockClass))->toBeFalse();
});
