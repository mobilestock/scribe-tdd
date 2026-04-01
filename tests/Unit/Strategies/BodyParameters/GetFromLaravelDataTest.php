<?php

use AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromLaravelData;

it('identifies body data class by default', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    // Class without docblock or query method -> should be body
    $mockClass = new ReflectionClass(
        new class extends Spatie\LaravelData\Data {
            public function __construct(public string $name = '')
            {
            }
        }
    );

    expect($reflection->invoke($strategy, $mockClass))->toBeTrue();
});

it('excludes data class with query parameters docblock', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    // Create a mock that has a "query parameters" docblock
    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');
    $mockClass->shouldReceive('hasMethod')->andReturn(false);

    expect($reflection->invoke($strategy, $mockClass))->toBeFalse();
});

it('excludes data class with queryParameters method but no bodyParameters', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn(false);
    $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
    $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(false);

    expect($reflection->invoke($strategy, $mockClass))->toBeFalse();
});

it('includes data class with both queryParameters and bodyParameters methods', function () {
    $strategy = new GetFromLaravelData(new Knuckles\Scribe\Tools\DocumentationConfig(config('scribe') ?? []));

    $reflection = new ReflectionMethod($strategy, 'isLaravelDataMeantForThisStrategy');

    $mockClass = Mockery::mock(ReflectionClass::class);
    $mockClass->shouldReceive('getDocComment')->andReturn(false);
    $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
    $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(true);

    expect($reflection->invoke($strategy, $mockClass))->toBeTrue();
});
