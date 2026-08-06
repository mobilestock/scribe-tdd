<?php

namespace AjCastro\ScribeTdd\Tests;

use AjCastro\ScribeTdd\Exceptions\LaravelNotPresent;
use Exception;
use PHPUnit\Metadata\Annotation\Parser\Registry;
use Illuminate\Support\Facades\File;
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
                    $generatedMarkerDirectory = $writeDir . '/.generated';
                    File::makeDirectory($generatedMarkerDirectory, 0755, true, true);
                    File::put($generatedMarkerDirectory . '/' . $filename, '');

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

        return is_array($existingData)
            && $this->structuresMatch(
                $this->comparisonStructure($existingData),
                $this->comparisonStructure($serializedData),
            );
    }

    private function comparisonStructure(array $data): array
    {
        return [
            'description' => $data['description'] ?? null,
            'responses' => array_map(
                fn(array $response) => [
                    'status' => $response['status'] ?? null,
                    'description' => $response['description'] ?? null,
                    'content' => $this->contentStructure($response['content'] ?? null),
                ],
                $data['responses'] ?? [],
            ),
        ];
    }

    private function contentStructure(mixed $content): mixed
    {
        if (!is_string($content)) {
            return $this->valueShape($content);
        }

        $decodedContent = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE
            ? $this->valueShape($decodedContent)
            : get_debug_type($content);
    }

    private function valueShape(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (!is_array($value)) {
            return get_debug_type($value);
        }

        if (array_is_list($value)) {
            $itemShapes = [];

            foreach ($value as $item) {
                $shape = $this->valueShape($item);
                $itemShapes[json_encode($shape)] = $shape;
            }

            ksort($itemShapes);

            return array_values($itemShapes);
        }

        $keys = array_keys($value);
        $hasOnlyNumericKeys = count(array_filter($keys, fn($key) => is_int($key))) === count($keys);

        if ($hasOnlyNumericKeys) {
            return $this->valueShape(array_values($value));
        }

        return array_map(fn($item) => $this->valueShape($item), $value);
    }

    private function structuresMatch(mixed $existing, mixed $generated): bool
    {
        if ($existing === 'null' || $generated === 'null') {
            return true;
        }

        if (!is_array($existing) || !is_array($generated)) {
            return $existing === $generated;
        }

        if (array_is_list($existing) !== array_is_list($generated)) {
            return false;
        }

        if (array_is_list($existing)) {
            return collect($existing)->every(
                fn($shape) => collect($generated)->contains(
                    fn($generatedShape) => $this->structuresMatch($shape, $generatedShape),
                ),
            ) && collect($generated)->every(
                fn($shape) => collect($existing)->contains(
                    fn($existingShape) => $this->structuresMatch($existingShape, $shape),
                ),
            );
        }

        if (array_keys($existing) !== array_keys($generated)) {
            return false;
        }

        return collect($existing)->every(
            fn($shape, $key) => $this->structuresMatch($shape, $generated[$key]),
        );
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
