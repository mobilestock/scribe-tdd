<?php

use AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromInlineValidator;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

beforeEach(function () {
    $this->strategy = new GetFromInlineValidator(new DocumentationConfig(config('scribe') ?? []));
});

it('is meant for GET routes', function () {
    $route = new Route(['GET'], 'items', fn() => null);
    $method = new ReflectionMethod(
        new class {
            public function index()
            {
            }
        },
        'index'
    );

    $endpoint = new ExtractedEndpointData([
        'route' => $route,
        'uri' => 'items',
        'httpMethods' => ['GET'],
        'method' => $method,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);

    $this->strategy->__invoke($endpoint);

    $reflection = new ReflectionMethod($this->strategy, 'isValidationStatementMeantForThisStrategy');
    $node = Mockery::mock(PhpParser\Node::class);

    expect($reflection->invoke($this->strategy, $node))->toBeTrue();
});

it('is not meant for non-GET routes', function () {
    $route = new Route(['POST'], 'items', fn() => null);
    $method = new ReflectionMethod(
        new class {
            public function store()
            {
            }
        },
        'store'
    );

    $endpoint = new ExtractedEndpointData([
        'route' => $route,
        'uri' => 'items',
        'httpMethods' => ['POST'],
        'method' => $method,
        'metadata' => [],
        'headers' => [],
        'urlParameters' => [],
        'queryParameters' => [],
        'bodyParameters' => [],
        'responses' => [],
        'responseFields' => [],
    ]);

    $this->strategy->__invoke($endpoint);

    $reflection = new ReflectionMethod($this->strategy, 'isValidationStatementMeantForThisStrategy');
    $node = Mockery::mock(PhpParser\Node::class);

    expect($reflection->invoke($this->strategy, $node))->toBeFalse();
});
