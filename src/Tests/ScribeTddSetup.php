<?php

namespace AjCastro\ScribeTdd\Tests;

use AjCastro\ScribeTdd\Exceptions\LaravelNotPresent;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use PHPUnit\Metadata\Annotation\Parser\Registry;
use Illuminate\Support\Facades\Artisan;
use Knuckles\Scribe\ScribeServiceProvider;
use Str;

trait ScribeTddSetup
{
    protected static $shutdownRegistered = false;

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

        if (App::environment('testing') && !self::$shutdownRegistered && empty($_SERVER['LARAVEL_PARALLEL_TESTING'])) {
            register_shutdown_function(fn() => $this->triggerScribeGeneration());
            self::$shutdownRegistered = true;
        }
    }

    public function triggerScribeGeneration(): void
    {
        $this->createApplication();

        $_SERVER['SCRIBE_TESTS'] = true;
        ScribeServiceProvider::$customTranslationLayerLoaded = false;
        Artisan::call('scribe:generate');
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
                    File::put($writeDir . '/' . $filename, json_encode($writeData, JSON_PRETTY_PRINT));
                }
            }
        }

        ExampleCreator::flushInstances();
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
