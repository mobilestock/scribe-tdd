<?php

use AjCastro\ScribeTdd\Tests\ArtifactReconciler;
use AjCastro\ScribeTdd\Tests\ArtifactStructureComparator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->generatedDirectory = storage_path('framework/testing/scribe-tdd-generated-artifacts');
    $this->committedDirectory = storage_path('framework/testing/scribe-tdd-committed-artifacts');
    File::deleteDirectory($this->generatedDirectory);
    File::deleteDirectory($this->committedDirectory);
    File::deleteDirectory($this->committedDirectory . '.staging');
    File::deleteDirectory($this->committedDirectory . '.previous');
    File::makeDirectory($this->generatedDirectory . '/route', 0755, true);
    File::makeDirectory($this->committedDirectory . '/route', 0755, true);
});

afterEach(function () {
    File::deleteDirectory($this->generatedDirectory);
    File::deleteDirectory($this->committedDirectory);
    File::deleteDirectory($this->committedDirectory . '.staging');
    File::deleteDirectory($this->committedDirectory . '.previous');
});

it('restores the previous artifact when structures are compatible', function () {
    $generatedPath = $this->generatedDirectory . '/route/example.json';
    $previousPath = $this->committedDirectory . '/route/example.json';
    $previous = json_encode(
        [
            'description' => 'example',
            'body_params' => ['id' => 1],
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'success',
                    'content' => '{"id":1}',
                ],
            ],
        ],
        JSON_PRETTY_PRINT
    );
    File::put($previousPath, $previous);
    File::put(
        $generatedPath,
        json_encode([
            'description' => 'example',
            'body_params' => ['id' => 2],
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'success',
                    'content' => '{"id":2}',
                ],
            ],
        ])
    );

    app(ArtifactReconciler::class)->commit($this->generatedDirectory, $this->committedDirectory);

    expect(File::get($this->committedDirectory . '/route/example.json'))
        ->toBe($previous)
        ->and(File::get($generatedPath))
        ->not->toBe($previous);
});

it('keeps generated artifacts that are new, changed, malformed, or not JSON', function () {
    $artifacts = [
        'new.json' => '{"description":"new"}',
        'changed.json' => '{"description":"changed","responses":[]}',
        'malformed.json' => '{malformed',
        'notes.txt' => 'generated notes',
    ];
    $previousArtifacts = [
        'changed.json' => '{"description":"previous","responses":[]}',
        'malformed.json' => '{"description":"previous","responses":[]}',
        'notes.txt' => 'previous notes',
    ];

    foreach ($artifacts as $filename => $contents) {
        File::put($this->generatedDirectory . '/route/' . $filename, $contents);
    }
    foreach ($previousArtifacts as $filename => $contents) {
        File::put($this->committedDirectory . '/route/' . $filename, $contents);
    }

    app(ArtifactReconciler::class)->commit($this->generatedDirectory, $this->committedDirectory);

    foreach ($artifacts as $filename => $contents) {
        expect(File::get($this->committedDirectory . '/route/' . $filename))->toBe($contents);
    }
});

it('merges generated artifacts into the committed snapshot when existing artifacts must be preserved', function () {
    File::put($this->generatedDirectory . '/route/selected.json', '{"description":"generated"}');
    File::put($this->committedDirectory . '/route/unrelated.json', '{"description":"unrelated"}');

    app(ArtifactReconciler::class)->commit($this->generatedDirectory, $this->committedDirectory, true);

    expect(File::get($this->committedDirectory . '/route/selected.json'))
        ->toBe('{"description":"generated"}')
        ->and(File::get($this->committedDirectory . '/route/unrelated.json'))
        ->toBe('{"description":"unrelated"}');
});

it('commits an empty snapshot when no generated artifacts exist', function () {
    File::deleteDirectory($this->generatedDirectory);
    File::put($this->committedDirectory . '/route/example.json', '{}');

    app(ArtifactReconciler::class)->commit($this->generatedDirectory, $this->committedDirectory);

    expect(File::allFiles($this->committedDirectory))->toBeEmpty();
});

it('restores the committed snapshot when installing the staged snapshot fails', function () {
    File::put($this->generatedDirectory . '/route/example.json', '{"id":2}');
    File::put($this->committedDirectory . '/route/example.json', '{"id":1}');
    $reconciler = new class (app(ArtifactStructureComparator::class)) extends ArtifactReconciler {
        private int $moveCount = 0;

        protected function moveDirectory(string $from, string $to, bool $overwrite = false): void
        {
            $this->moveCount++;

            if ($this->moveCount === 2) {
                throw new RuntimeException('Unable to install staged artifacts.');
            }

            parent::moveDirectory($from, $to, $overwrite);
        }
    };

    expect(fn() => $reconciler->commit($this->generatedDirectory, $this->committedDirectory))->toThrow(
        RuntimeException::class,
        'Unable to install staged artifacts.'
    );
    expect(File::get($this->committedDirectory . '/route/example.json'))->toBe('{"id":1}');
});
