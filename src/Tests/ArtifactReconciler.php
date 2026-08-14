<?php

namespace AjCastro\ScribeTdd\Tests;

use Illuminate\Filesystem\Filesystem;

class ArtifactReconciler
{
    private readonly Filesystem $files;

    public function __construct(private readonly ArtifactStructureComparator $comparator)
    {
        $this->files = new Filesystem();
    }

    public function commit(
        string $generatedArtifactDirectory,
        string $committedArtifactDirectory,
        bool $preserveExistingArtifacts = false
    ): void {
        $stagingDirectory = $committedArtifactDirectory . '.staging';
        $previousDirectory = $committedArtifactDirectory . '.previous';
        $this->files->deleteDirectory($stagingDirectory);
        $this->files->deleteDirectory($previousDirectory);
        $this->files->makeDirectory($stagingDirectory, 0755, true);

        if ($preserveExistingArtifacts && $this->files->isDirectory($committedArtifactDirectory)) {
            $this->files->copyDirectory($committedArtifactDirectory, $stagingDirectory);
        }

        if ($this->files->isDirectory($generatedArtifactDirectory)) {
            $this->files->copyDirectory($generatedArtifactDirectory, $stagingDirectory);
        }

        foreach ($this->files->allFiles($stagingDirectory) as $generatedArtifact) {
            if ($generatedArtifact->getExtension() !== 'json') {
                continue;
            }

            $relativePath = $generatedArtifact->getRelativePathname();
            $previousArtifactPath = $committedArtifactDirectory . DIRECTORY_SEPARATOR . $relativePath;

            if (!$this->files->isFile($previousArtifactPath)) {
                continue;
            }

            $generated = json_decode($this->files->get($generatedArtifact->getPathname()), true);
            $previous = json_decode($this->files->get($previousArtifactPath), true);

            if (
                is_array($generated) &&
                is_array($previous) &&
                $this->comparator->areCompatible($previous, $generated)
            ) {
                $this->files->copy($previousArtifactPath, $generatedArtifact->getPathname());
            }
        }

        if ($this->files->isDirectory($committedArtifactDirectory)) {
            $this->moveDirectory($committedArtifactDirectory, $previousDirectory);
        }

        try {
            $this->moveDirectory($stagingDirectory, $committedArtifactDirectory);
            $this->files->deleteDirectory($previousDirectory);
        } catch (\Throwable $exception) {
            if ($this->files->isDirectory($previousDirectory)) {
                $this->moveDirectory($previousDirectory, $committedArtifactDirectory, true);
            }

            throw $exception;
        }
    }

    public function discard(string $generatedArtifactDirectory): void
    {
        $this->files->deleteDirectory($generatedArtifactDirectory);
    }

    protected function moveDirectory(string $from, string $to, bool $overwrite = false): void
    {
        $this->files->moveDirectory($from, $to, $overwrite);
    }
}
