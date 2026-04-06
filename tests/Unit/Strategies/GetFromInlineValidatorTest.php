<?php

use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;

dataset('inlineValidatorStrategies', [
    'BodyParameters accepts POST' => [
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromInlineValidator::class,
        ['POST'],
        true,
    ],
    'BodyParameters rejects GET' => [
        AjCastro\ScribeTdd\Strategies\BodyParameters\GetFromInlineValidator::class,
        ['GET'],
        false,
    ],
    'QueryParameters accepts GET' => [
        AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromInlineValidator::class,
        ['GET'],
        true,
    ],
    'QueryParameters rejects POST' => [
        AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromInlineValidator::class,
        ['POST'],
        false,
    ],
]);

it('validates route method correctly', function (string $class, array $httpMethods, bool $expected) {
    $strategy = new $class(new DocumentationConfig(config('scribe') ?? []));

    $route = new Route($httpMethods, 'items', fn() => null);

    $endpoint = makeEndpointData([
        'route' => $route,
        'uri' => 'items',
        'httpMethods' => $httpMethods,
        'method' => new ReflectionMethod(
            new class {
                public function handle()
                {
                }
            },
            'handle'
        ),
    ]);

    $strategy->__invoke($endpoint);

    $reflection = new ReflectionMethod($strategy, 'isValidationStatementMeantForThisStrategy');
    $node = Mockery::mock(PhpParser\Node::class);

    $result = $reflection->invoke($strategy, $node);

    expect($result)->toBe($expected);
})->with('inlineValidatorStrategies');
