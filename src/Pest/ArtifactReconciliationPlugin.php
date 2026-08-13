<?php

namespace AjCastro\ScribeTdd\Pest;

use AjCastro\ScribeTdd\Tests\ArtifactReconciler;
use AjCastro\ScribeTdd\Tests\ArtifactStructureComparator;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel;

class ArtifactReconciliationPlugin implements AddsOutput, HandlesArguments
{
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
                $reconciler->commit($this->generatedDirectory(), $this->committedDirectory());
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
