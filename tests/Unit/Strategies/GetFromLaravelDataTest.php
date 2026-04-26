<?php

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\DocumentationConfig;

dataset('http methods without body', ['GET', 'HEAD', 'DELETE']);

function setEndpointDataWithHttpMethod(Strategy $strategy, string $httpMethod): void
{
    $endpointData = Mockery::mock(ExtractedEndpointData::class);
    $endpointData->httpMethods = [$httpMethod];

    $strategy->endpointData = $endpointData;
}

describe('BodyParameters', function () {
    beforeEach(function () {
        $this->strategy = new AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromLaravelData(
            new DocumentationConfig(config('scribe') ?? [])
        );
        $this->reflection = new ReflectionMethod($this->strategy, 'isLaravelDataMeantForThisStrategy');
    });

    it('should identify body data class by default for POST requests', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = new ReflectionClass(
            new class extends Spatie\LaravelData\Data {
                public function __construct(public string $name = '')
                {
                }
            }
        );

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeTrue();
    });

    it('should exclude data class for HTTP methods without body', function (string $httpMethod) {
        setEndpointDataWithHttpMethod($this->strategy, $httpMethod);

        $mockClass = new ReflectionClass(
            new class extends Spatie\LaravelData\Data {
                public function __construct(public string $name = '')
                {
                }
            }
        );

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeFalse();
    })->with('http methods without body');

    it('should exclude data class with query parameters docblock', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');
        $mockClass->shouldReceive('hasMethod')->andReturn(false);

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeFalse();
    });

    it('should exclude data class with queryParameters method but no bodyParameters', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
        $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(false);

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeFalse();
    });

    it('should include data class with both queryParameters and bodyParameters methods', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);
        $mockClass->shouldReceive('hasMethod')->with('bodyParameters')->andReturn(true);

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeTrue();
    });
});

describe('QueryParameters', function () {
    beforeEach(function () {
        $this->strategy = new AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromLaravelData(
            new DocumentationConfig(config('scribe') ?? [])
        );
        $this->reflection = new ReflectionMethod($this->strategy, 'isLaravelDataMeantForThisStrategy');
    });

    it('should include data class for HTTP methods without body', function (string $httpMethod) {
        setEndpointDataWithHttpMethod($this->strategy, $httpMethod);

        $mockClass = new ReflectionClass(
            new class extends Spatie\LaravelData\Data {
                public function __construct(public string $name = '')
                {
                }
            }
        );

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeTrue();
    })->with('http methods without body');

    it('should exclude data class for POST requests without query indicators', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(false);

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeFalse();
    });

    it('should include data class with query parameters docblock', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn('/** Query parameters */');

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeTrue();
    });

    it('should include data class with queryParameters method', function () {
        setEndpointDataWithHttpMethod($this->strategy, 'POST');

        $mockClass = Mockery::mock(ReflectionClass::class);
        $mockClass->shouldReceive('getDocComment')->andReturn(false);
        $mockClass->shouldReceive('hasMethod')->with('queryParameters')->andReturn(true);

        $result = $this->reflection->invoke($this->strategy, $mockClass);

        expect($result)->toBeTrue();
    });
});
