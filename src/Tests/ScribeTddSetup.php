<?php

namespace AjCastro\ScribeTdd\Tests;

use AjCastro\ScribeTdd\Exceptions\LaravelNotPresent;
use Exception;
use Illuminate\Support\Facades\File;
use PHPUnit\Metadata\Annotation\Parser\Registry;
use Str;

trait ScribeTddSetup
{
    public function setUpScribeTdd(): void
    {
        if (!config('scribe-tdd.enabled')) {
            return;
        }

        if (empty($this->app)) {
            throw new LaravelNotPresent();
        }

        $this->afterApplicationCreated(function () {
            if (!$this->shouldSkipExample()) {
                $this->makeExample();
            }
        });

        $this->beforeApplicationDestroyed(function () {
            $this->writeExample();
        });
    }

    private function makeExample(): void
    {
        $exampleCreator = new ExampleCreator([
            'test' => $this,
            'testMethod' => $this->name(),
            'dataName' => $this->dataName(),
            'providedData' => $this->providedData(),
            'description' => $this->guessResponseDescription($this->name()),
        ]);

        ExampleCreator::setCurrentInstance($exampleCreator);
    }

    private function writeExample()
    {
        $instancesByRoute = ExampleCreator::getInstances();
        foreach ($instancesByRoute as $instances) {
            foreach ($instances as $instance) {
                $writeDir = $instance->writeDir($instance->route);
                File::makeDirectory($writeDir, 0755, true, true);

                foreach ($instance->getWritables() as $filename => $writeData) {
                    $writePath = $writeDir . '/' . $filename;

                    if ($this->hasSameStructure($writePath, $writeData)) {
                        continue;
                    }

                    File::put($writePath, json_encode($writeData, JSON_PRETTY_PRINT));
                }
            }
        }

        ExampleCreator::flushInstances();
    }

    private function hasSameStructure(string $path, array $data): bool
    {
        if (!File::exists($path)) {
            return false;
        }

        $existingData = json_decode(File::get($path), true);
        $serializedData = json_decode(json_encode($data), true);

        return is_array($existingData) &&
            app(ArtifactStructureComparator::class)->areCompatible($existingData, $serializedData);
    }

    private function shouldSkipExample(): bool
    {
        return !is_null($this->getAnnotation($this->name(), 'scribeSkip'));
    }

    private function guessResponseDescription($testMethod)
    {
        $description = $this->getAnnotation($testMethod, 'scribeDescription')[0] ?? null;

        if ($description) {
            return $description;
        }

        if (Str::startsWith($testMethod, 'test')) {
            $testMethod = mb_substr($testMethod, 4);
        }

        return trim(str_replace('_', ' ', Str::snake($testMethod)));
    }

    private function getAnnotation($testMethod, $name): ?array
    {
        $annotations = self::parseTestMethodAnnotations(static::class, $testMethod);

        return $annotations['method'][$name] ?? null;
    }

    public function getName(bool $withDataSet = true): string
    {
        try {
            return parent::getName($withDataSet);
        } catch (\Throwable) {
            return $this->name();
        }
    }

    public function getProvidedData(): array
    {
        try {
            return parent::getProvidedData();
        } catch (\Throwable) {
            return $this->providedData();
        }
    }

    public static function parseTestMethodAnnotations(string $className, ?string $methodName = null): array
    {
        $registry = Registry::getInstance();

        if ($methodName !== null) {
            try {
                return [
                    'method' => $registry->forMethod($className, $methodName)->symbolAnnotations(),
                    'class' => $registry->forClassName($className)->symbolAnnotations(),
                ];
            } catch (Exception $methodNotFound) {
                // ignored
            }
        }

        return [
            'method' => null,
            'class' => $registry->forClassName($className)->symbolAnnotations(),
        ];
    }
}
