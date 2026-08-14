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

it('detects concrete response structure discovered after a committed null', function () {
    $existing = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"profile":null}']],
    ];
    $generated = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"profile":{"id":1}}']],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeFalse();
});

it('accepts a generated null for committed concrete response structure', function () {
    $existing = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"profile":{"id":1}}']],
    ];
    $generated = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"profile":null}']],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeTrue();
});

it('detects an enum array replacing an ordinary string array', function () {
    $existing = [
        'description' => 'example',
        'responses' => [
            [
                'status' => 200,
                'description' => 'success',
                'content' => '{"methods":["legacy","manual"]}',
            ],
        ],
    ];
    $generated = [
        'description' => 'example',
        'responses' => [
            [
                'status' => 200,
                'description' => 'success',
                'content' => '{"methods":["EMAIL","PHONE_NUMBER"]}',
                'content_enum_paths' => [
                    ['path' => ['methods', 0], 'class' => 'App\\Enum\\LoginMethod'],
                    ['path' => ['methods', 1], 'class' => 'App\\Enum\\LoginMethod'],
                ],
            ],
        ],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeFalse();
});

it('ignores ordinary string array example value changes', function () {
    $existing = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"methods":["legacy"]}']],
    ];
    $generated = [
        'description' => 'example',
        'responses' => [['status' => 200, 'description' => 'success', 'content' => '{"methods":["current"]}']],
    ];

    $compatible = (new ArtifactStructureComparator())->areCompatible($existing, $generated);

    expect($compatible)->toBeTrue();
});
