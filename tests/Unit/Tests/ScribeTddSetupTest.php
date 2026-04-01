<?php

use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\Tests\ScribeTddSetup;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

describe('guessResponseDescription', function () {
    it('strips test prefix and converts to human-readable', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callGuessResponseDescription(string $testMethod): string
            {
                return $this->guessResponseDescription($testMethod);
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                return null;
            }
        };

        expect($trait->callGuessResponseDescription('testCreateOrder'))
            ->toBe('create order');
    });

    it('converts snake_case method name', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callGuessResponseDescription(string $testMethod): string
            {
                return $this->guessResponseDescription($testMethod);
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                return null;
            }
        };

        expect($trait->callGuessResponseDescription('test_create_order'))
            ->toBe('create order');
    });

    it('uses scribeDescription annotation when available', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callGuessResponseDescription(string $testMethod): string
            {
                return $this->guessResponseDescription($testMethod);
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                if ($name === 'scribeDescription') {
                    return ['Custom description'];
                }
                return null;
            }
        };

        expect($trait->callGuessResponseDescription('testSomething'))
            ->toBe('Custom description');
    });

    it('handles method not starting with test', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callGuessResponseDescription(string $testMethod): string
            {
                return $this->guessResponseDescription($testMethod);
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                return null;
            }
        };

        expect($trait->callGuessResponseDescription('createOrder'))
            ->toBe('create order');
    });
});

describe('shouldSkipExample', function () {
    it('returns false when no scribeSkip annotation', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callShouldSkipExample(): bool
            {
                return $this->shouldSkipExample();
            }

            public function name(bool $withDataSet = true): string
            {
                return 'testNormal';
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                return null;
            }
        };

        expect($trait->callShouldSkipExample())->toBeFalse();
    });

    it('returns true when scribeSkip annotation present', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function callShouldSkipExample(): bool
            {
                return $this->shouldSkipExample();
            }

            public function name(bool $withDataSet = true): string
            {
                return 'testSkipped';
            }

            private function getAnnotation($testMethod, $name): ?array
            {
                if ($name === 'scribeSkip') {
                    return [''];
                }
                return null;
            }
        };

        expect($trait->callShouldSkipExample())->toBeTrue();
    });
});

describe('parseTestMethodAnnotations', function () {
    it('returns annotations for class when no method given', function () {
        $trait = new class {
            use ScribeTddSetup;
        };

        $result = $trait::parseTestMethodAnnotations(get_class($trait));

        expect($result)->toHaveKey('class');
        expect($result['method'])->toBeNull();
    });

    it('returns method annotations when method exists', function () {
        $trait = new class {
            use ScribeTddSetup;

            /** @scribeDescription Test description */
            public function testExample() {}
        };

        $result = $trait::parseTestMethodAnnotations(get_class($trait), 'testExample');

        expect($result)->toHaveKey('method');
        expect($result)->toHaveKey('class');
    });

    it('handles nonexistent method gracefully', function () {
        $trait = new class {
            use ScribeTddSetup;
        };

        $result = $trait::parseTestMethodAnnotations(get_class($trait), 'nonExistentMethod');

        expect($result)->toHaveKey('class');
    });
});

describe('setUpScribeTdd', function () {
    it('returns early when scribe-tdd is disabled', function () {
        Config::set('scribe-tdd.enabled', false);

        $trait = new class {
            use ScribeTddSetup;

            public $app;

            public function callSetUp(): void
            {
                $this->setUpScribeTdd();
            }

            public function afterApplicationCreated(callable $callback) {}
            public function beforeApplicationDestroyed(callable $callback) {}
        };
        $trait->app = app();

        $trait->callSetUp();

        expect(ExampleCreator::$currentInstance)->toBeNull();

        Config::set('scribe-tdd.enabled', true);
    });

    it('registers callbacks when enabled', function () {
        Config::set('scribe-tdd.enabled', true);

        $afterCallback = null;
        $beforeCallback = null;

        $trait = new class {
            use ScribeTddSetup;

            public $app;
            public $afterCb;
            public $beforeCb;

            public function callSetUp(): void
            {
                // Reset shutdown registration to avoid side effects
                self::$shutdownRegistered = true;
                $this->setUpScribeTdd();
            }

            public function afterApplicationCreated(callable $callback)
            {
                $this->afterCb = $callback;
            }

            public function beforeApplicationDestroyed(callable $callback)
            {
                $this->beforeCb = $callback;
            }

            // Stubs needed by makeExample
            public function name(bool $withDataSet = true): string
            {
                return 'test_example_method';
            }

            public function dataName(): string
            {
                return '';
            }

            public function providedData(): array
            {
                return [];
            }
        };
        $trait->app = app();

        $trait->callSetUp();

        // afterApplicationCreated callback should have been captured
        expect($trait->afterCb)->toBeCallable();
        expect($trait->beforeCb)->toBeCallable();

        // Execute the afterApplicationCreated callback - this calls makeExample
        ($trait->afterCb)();
        expect(ExampleCreator::$currentInstance)->not->toBeNull();
        expect(ExampleCreator::$currentInstance->testMethod)->toBe('test_example_method');

        // Execute the beforeApplicationDestroyed callback - this calls writeExample
        ($trait->beforeCb)();
        // After writeExample, instances should be flushed
        expect(ExampleCreator::getInstances())->toBeEmpty();
    });

    it('registers shutdown function when not already registered', function () {
        Config::set('scribe-tdd.enabled', true);

        $trait = new class {
            use ScribeTddSetup;

            public $app;
            public $afterCb;
            public $beforeCb;

            public function callSetUp(): void
            {
                self::$shutdownRegistered = false;
                $this->setUpScribeTdd();
            }

            public function afterApplicationCreated(callable $callback) { $this->afterCb = $callback; }
            public function beforeApplicationDestroyed(callable $callback) { $this->beforeCb = $callback; }
            public function name(bool $withDataSet = true): string { return 'test_method'; }
            public function dataName(): string { return ''; }
            public function providedData(): array { return []; }
        };
        $trait->app = app();

        $trait->callSetUp();

        // Verify shutdownRegistered was set to true (line 48)
        $prop = new ReflectionProperty($trait, 'shutdownRegistered');
        expect($prop->getValue())->toBeTrue();
    });

    it('throws LaravelNotPresent when app is empty', function () {
        Config::set('scribe-tdd.enabled', true);

        $trait = new class {
            use ScribeTddSetup;

            public $app = null;

            public function callSetUp(): void
            {
                $this->setUpScribeTdd();
            }
        };

        expect(fn () => $trait->callSetUp())
            ->toThrow(\AjCastro\ScribeTdd\Exceptions\LaravelNotPresent::class);
    });
});

describe('writeExample', function () {
    it('writes example data to storage', function () {
        $route = new \Illuminate\Routing\Route(['POST'], 'write-test', fn () => null);
        $route->bind(\Illuminate\Http\Request::create('/write-test'));

        $test = Mockery::mock(\Illuminate\Foundation\Testing\TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_write',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test write',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = \Illuminate\Http\Request::create('/write-test', 'POST', ['name' => 'test']);
        $request->setRouteResolver(fn () => $route);
        $response = new \Illuminate\Http\Response('{"ok":true}', 200);

        $exampleRequest = new \AjCastro\ScribeTdd\Tests\ExampleRequest($request, $response, $instance);
        $instance->addExampleRequest($exampleRequest);

        // Call writeExample via reflection
        $trait = new class {
            use ScribeTddSetup;
        };
        $reflection = new ReflectionMethod($trait, 'writeExample');
        $reflection->invoke($trait);

        $dir = ExampleCreator::writeDir($route);
        expect(File::isDirectory($dir))->toBeTrue();

        // Clean up
        File::deleteDirectory($dir);
    });
});

describe('getName', function () {
    it('falls back to name() method on throwable', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function name(bool $withDataSet = true): string
            {
                return 'fallback_name';
            }

            public function callGetName(): string
            {
                return $this->getName();
            }
        };

        // Since there's no parent::getName(), it should throw and fall back to name()
        expect($trait->callGetName())->toBe('fallback_name');
    });
});

describe('getProvidedData', function () {
    it('falls back to providedData() method on throwable', function () {
        $trait = new class {
            use ScribeTddSetup;

            public function providedData(): array
            {
                return ['key' => 'value'];
            }

            public function callGetProvidedData(): array
            {
                return $this->getProvidedData();
            }
        };

        // Since there's no parent::getProvidedData(), it should throw and fall back
        expect($trait->callGetProvidedData())->toBe(['key' => 'value']);
    });
});
