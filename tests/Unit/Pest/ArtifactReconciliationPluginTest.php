<?php

use AjCastro\ScribeTdd\Pest\ArtifactReconciliationPlugin;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->committedDirectory = storage_path('framework/testing/pest-committed-artifacts');
    putenv('SCRIBE_TDD_COMMITTED_ARTIFACT_DIRECTORY=' . $this->committedDirectory);
    unset($_SERVER['PARATEST'], $_ENV['PARATEST']);
    File::deleteDirectory($this->committedDirectory);
});

afterEach(function () {
    putenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    putenv('SCRIBE_TDD_COMMITTED_ARTIFACT_DIRECTORY');
    unset(
        $_SERVER['PARATEST'],
        $_ENV['PARATEST'],
        $_SERVER['SCRIBE_TDD_ARTIFACT_DIRECTORY'],
        $_ENV['SCRIBE_TDD_ARTIFACT_DIRECTORY']
    );
    File::deleteDirectory($this->committedDirectory);
});

it('creates an isolated artifact directory inside the test process', function () {
    $plugin = new ArtifactReconciliationPlugin();

    $arguments = $plugin->handleArguments(['--parallel']);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');

    expect($arguments)
        ->toBe(['--parallel'])
        ->and($generatedDirectory)
        ->toStartWith(sys_get_temp_dir() . '/scribe-tdd-')
        ->and(File::isDirectory($generatedDirectory))
        ->toBeTrue()
        ->and($_ENV['SCRIBE_TDD_ARTIFACT_DIRECTORY'])
        ->toBe($generatedDirectory)
        ->and($_SERVER['SCRIBE_TDD_ARTIFACT_DIRECTORY'])
        ->toBe($generatedDirectory);

    File::deleteDirectory($generatedDirectory);
});

it('commits generated artifacts after a successful test run', function () {
    $plugin = new ArtifactReconciliationPlugin();
    $plugin->handleArguments([]);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    File::makeDirectory($generatedDirectory . '/route', 0755, true);
    File::put($generatedDirectory . '/route/example.json', '{"description":"example"}');

    $exitCode = $plugin->addOutput(0);

    expect($exitCode)
        ->toBe(0)
        ->and(File::get($this->committedDirectory . '/route/example.json'))
        ->toBe('{"description":"example"}')
        ->and(File::isDirectory($generatedDirectory))
        ->toBeFalse();
});

it('replaces the committed snapshot when running the complete tests directory', function () {
    $plugin = new ArtifactReconciliationPlugin();
    $plugin->handleOriginalArguments(['vendor/bin/pest', 'tests', '--coverage']);
    $plugin->handleArguments([]);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    File::makeDirectory($generatedDirectory . '/route', 0755, true);
    File::makeDirectory($this->committedDirectory . '/route', 0755, true);
    File::put($generatedDirectory . '/route/current.json', '{"description":"current"}');
    File::put($this->committedDirectory . '/route/stale.json', '{"description":"stale"}');

    $exitCode = $plugin->addOutput(0);

    expect($exitCode)
        ->toBe(0)
        ->and(File::get($this->committedDirectory . '/route/current.json'))
        ->toBe('{"description":"current"}')
        ->and(File::exists($this->committedDirectory . '/route/stale.json'))
        ->toBeFalse();
});

it('preserves unrelated committed artifacts after a successful filtered test run', function () {
    $plugin = new ArtifactReconciliationPlugin();
    $plugin->handleOriginalArguments(['vendor/bin/pest', '--filter=selected test']);
    $plugin->handleArguments([]);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    File::makeDirectory($generatedDirectory . '/route', 0755, true);
    File::makeDirectory($this->committedDirectory . '/route', 0755, true);
    File::put($generatedDirectory . '/route/selected.json', '{"description":"generated"}');
    File::put($this->committedDirectory . '/route/selected.json', '{"description":"committed"}');
    File::put($this->committedDirectory . '/route/unrelated.json', '{"description":"unrelated"}');

    $exitCode = $plugin->addOutput(0);

    expect($exitCode)
        ->toBe(0)
        ->and(File::get($this->committedDirectory . '/route/selected.json'))
        ->toBe('{"description":"generated"}')
        ->and(File::get($this->committedDirectory . '/route/unrelated.json'))
        ->toBe('{"description":"unrelated"}');
});

it('preserves unrelated committed artifacts after running a selected test path', function (string $testPath) {
    $plugin = new ArtifactReconciliationPlugin();
    $plugin->handleOriginalArguments(['vendor/bin/pest', $testPath]);
    $plugin->handleArguments([]);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    File::makeDirectory($generatedDirectory . '/route', 0755, true);
    File::makeDirectory($this->committedDirectory . '/route', 0755, true);
    File::put($generatedDirectory . '/route/selected.json', '{"description":"generated"}');
    File::put($this->committedDirectory . '/route/unrelated.json', '{"description":"unrelated"}');

    $exitCode = $plugin->addOutput(0);

    expect($exitCode)
        ->toBe(0)
        ->and(File::get($this->committedDirectory . '/route/selected.json'))
        ->toBe('{"description":"generated"}')
        ->and(File::get($this->committedDirectory . '/route/unrelated.json'))
        ->toBe('{"description":"unrelated"}');
})->with([
    'test file' => 'tests/Feature/SelectedTest.php',
    'nested test directory' => 'tests/Unit',
]);

it('leaves committed artifacts untouched and discards generated artifacts after a failed test run', function () {
    $plugin = new ArtifactReconciliationPlugin();
    $plugin->handleArguments([]);
    $generatedDirectory = getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    File::makeDirectory($this->committedDirectory, 0755, true);
    File::put($generatedDirectory . '/example.json', '{"id":2}');
    File::put($this->committedDirectory . '/example.json', '{"id":1}');

    $exitCode = $plugin->addOutput(1);

    expect($exitCode)
        ->toBe(1)
        ->and(File::get($this->committedDirectory . '/example.json'))
        ->toBe('{"id":1}')
        ->and(File::isDirectory($generatedDirectory))
        ->toBeFalse();
});

it('does not reconcile artifacts in a parallel worker', function () {
    $_SERVER['PARATEST'] = 1;
    $generatedDirectory = storage_path('framework/testing/pest-worker-artifacts');
    putenv('SCRIBE_TDD_ARTIFACT_DIRECTORY=' . $generatedDirectory);
    File::makeDirectory($generatedDirectory, 0755, true);
    File::put($generatedDirectory . '/example.json', '{}');
    $plugin = new ArtifactReconciliationPlugin();

    $plugin->handleArguments([]);
    $exitCode = $plugin->addOutput(0);

    expect($exitCode)
        ->toBe(0)
        ->and(File::exists($generatedDirectory . '/example.json'))
        ->toBeTrue()
        ->and(File::isDirectory($this->committedDirectory))
        ->toBeFalse();

    File::deleteDirectory($generatedDirectory);
});
