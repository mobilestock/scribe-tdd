<?php

use AjCastro\ScribeTdd\Tests\ArtifactStructureComparator;

it('detects request parameter schema changes', function (string $parameterGroup) {
    $existing = [
        'description' => 'example',
        'url_params' => [],
        'query_params' => [],
        'body_params' => [],
        'responses' => [],
    ];
    $generated = $existing;
    $existing[$parameterGroup] = [
        'id' => ['type' => 'integer', 'description' => '', 'example' => 1],
    ];
    $generated[$parameterGroup] = [
        'uuid' => ['type' => 'string', 'description' => '', 'example' => 'abc'],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeFalse();
})->with([
    'URL parameters' => 'url_params',
    'query parameters' => 'query_params',
    'body parameters' => 'body_params',
]);

it('detects request parameter type changes', function (string $parameterGroup) {
    $existing = [
        'description' => 'example',
        'url_params' => [],
        'query_params' => [],
        'body_params' => [],
        'responses' => [],
    ];
    $generated = $existing;
    $existing[$parameterGroup] = [
        'id' => ['type' => 'integer', 'description' => '', 'example' => 1],
    ];
    $generated[$parameterGroup] = [
        'id' => ['type' => 'string', 'description' => '', 'example' => '1'],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeFalse();
})->with([
    'URL parameters' => 'url_params',
    'query parameters' => 'query_params',
    'body parameters' => 'body_params',
]);

it('ignores request parameter example value changes', function (string $parameterGroup) {
    $existing = [
        'description' => 'example',
        'url_params' => [],
        'query_params' => [],
        'body_params' => [],
        'responses' => [],
    ];
    $generated = $existing;
    $existing[$parameterGroup] = [
        'id' => ['type' => 'integer', 'description' => '', 'example' => 1],
    ];
    $generated[$parameterGroup] = [
        'id' => ['type' => 'integer', 'description' => '', 'example' => 2],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeTrue();
})->with([
    'URL parameters' => 'url_params',
    'query parameters' => 'query_params',
    'body parameters' => 'body_params',
]);
