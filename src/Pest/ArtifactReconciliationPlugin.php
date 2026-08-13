<?php

namespace AjCastro\ScribeTdd\Pest;

use AjCastro\ScribeTdd\Tests\ArtifactReconciler;
use AjCastro\ScribeTdd\Tests\ArtifactStructureComparator;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\HandlesOriginalArguments;
use Pest\Plugins\Parallel;

class ArtifactReconciliationPlugin implements AddsOutput, HandlesArguments, HandlesOriginalArguments
{
    private bool $partialRun = false;

    public function handleOriginalArguments(array $arguments): void
    {
        $this->partialRun = array_any(
            array_slice($arguments, 1),
            fn(string $argument) => str_starts_with($argument, '--filter') ||
                is_file($argument) ||
                is_dir($argument) ||
                str_ends_with($argument, '.php')
        );
    }

    public function handleArguments(array $arguments): array
    {
        if (!Parallel::isWorker()) {
            $generatedDirectory = sys_get_temp_dir() . '/scribe-tdd-' . bin2hex(random_bytes(16));
            mkdir($generatedDirectory, 0755, true);

            putenv('SCRIBE_TDD_ARTIFACT_DIRECTORY=' . $generatedDirectory);
            $_ENV['SCRIBE_TDD_ARTIFACT_DIRECTORY'] = $generatedDirectory;
            $_SERVER['SCRIBE_TDD_ARTIFACT_DIRECTORY'] = $generatedDirectory;
        }

        return $arguments;
    }

    public function addOutput(int $exitCode): int
    {
        if (!Parallel::isWorker()) {
            $reconciler = new ArtifactReconciler(new ArtifactStructureComparator());

            if ($exitCode === 0) {
                $reconciler->commit($this->generatedDirectory(), $this->committedDirectory(), $this->partialRun);
            }

            $reconciler->discard($this->generatedDirectory());
        }

        return $exitCode;
    }

    private function generatedDirectory(): string
    {
        return getenv('SCRIBE_TDD_ARTIFACT_DIRECTORY');
    }

    private function committedDirectory(): string
    {
        return getenv('SCRIBE_TDD_COMMITTED_ARTIFACT_DIRECTORY') ?: getcwd() . '/storage/scribe-tdd';
    }
}
