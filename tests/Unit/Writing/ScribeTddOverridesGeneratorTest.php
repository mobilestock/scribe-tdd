<?php

use AjCastro\ScribeTdd\Writing\ScribeTddOverridesGenerator;
use Knuckles\Scribe\Tools\DocumentationConfig;

describe('root', function () {
    it('should merge overrides into root spec', function () {
        $generator = new ScribeTddOverridesGenerator(
            new DocumentationConfig([
                'openapi' => [
                    'overrides' => [
                        'info.title' => 'Custom API',
                        'info.version' => '2.0.0',
                    ],
                ],
            ])
        );
        $root = ['info' => ['title' => 'Original', 'description' => 'Desc']];

        $result = $generator->root($root, []);

        expect($result['info']['title'])
            ->toBe('Custom API')
            ->and($result['info']['version'])
            ->toBe('2.0.0')
            ->and($result['info']['description'])
            ->toBe('Desc');
    });

    it('should return root unchanged when overrides is empty', function () {
        $generator = new ScribeTddOverridesGenerator(
            new DocumentationConfig([
                'openapi' => ['overrides' => []],
            ])
        );
        $root = ['info' => ['title' => 'API']];

        $result = $generator->root($root, []);

        expect($result)->toBe($root);
    });

    it('should return root unchanged when overrides key is missing', function () {
        $generator = new ScribeTddOverridesGenerator(new DocumentationConfig([]));
        $root = ['info' => ['title' => 'API']];

        $result = $generator->root($root, []);

        expect($result)->toBe($root);
    });
});
