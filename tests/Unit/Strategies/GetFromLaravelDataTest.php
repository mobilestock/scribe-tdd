<?php

use Knuckles\Scribe\Tools\DocumentationConfig;

describe('BodyParameters', function () {
    beforeEach(function () {
        $this->strategy = new AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromLaravelData(
            new DocumentationConfig(config('scribe') ?? [])
        );
        $this->reflection = new ReflectionMethod($this->strategy, 'isLaravelDataMeantForThisStrategy');
    });

    it('identifies body data class by default', function () {
        $mockClass = new ReflectionClass(
            new class extends Spatie\LaravelData\Data {
                public function __construct(public string $name = '')
                {
                }
            }
        );

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeTrue();
    });

    it('excludes data class with query parameters docblock', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');
        $mockClass->shouldReceive('hasMethod')->andReturn(false);

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeFalse();
    });

    it('excludes data class with queryParameters method but no bodyParameters', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
        $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(false);

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeFalse();
    });

    it('includes data class with both queryParameters and bodyParameters methods', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
        $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(true);

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeTrue();
    });
});

describe('QueryParameters', function () {
    beforeEach(function () {
        $this->strategy = new AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromLaravelData(
            new DocumentationConfig(config('scribe') ?? [])
        );
        $this->reflection = new ReflectionMethod($this->strategy, 'isLaravelDataMeantForThisStrategy');
    });

    it('includes data class with query parameters docblock', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeTrue();
    });

    it('includes data class with queryParameters method', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeTrue();
    });

    it('excludes data class without query indicators', function () {
        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(false);

        expect($this->reflection->invoke($this->strategy, $mockClass))->toBeFalse();
    });
});
