<?php

use AjCastro\ScribeTdd\Exceptions\LaravelNotPresent;
use AjCastro\ScribeTdd\Tests\ExampleCreator;
use AjCastro\ScribeTdd\Tests\ExampleRequest;
use AjCastro\ScribeTdd\Tests\ScribeTddSetup;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Knuckles\Scribe\ScribeServiceProvider;

beforeEach(function () {
    ExampleCreator::flushInstances();
    ExampleCreator::$currentInstance = null;
});

describe('guessResponseDescription', function () {
    beforeEach(function () {
        $this->trait = new class {
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
    });

    it('strips test prefix and converts to human-readable', function () {
        $result = $this->trait->callGuessResponseDescription('testCreateOrder');

        expect($result)->toBe('create order');
    });

    it('converts snake_case method name', function () {
        $result = $this->trait->callGuessResponseDescription('test_create_order');

        expect($result)->toBe('create order');
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

        $result = $trait->callGuessResponseDescription('testSomething');

        expect($result)->toBe('Custom description');
    });

    it('handles method not starting with test', function () {
        $result = $this->trait->callGuessResponseDescription('createOrder');

        expect($result)->toBe('create order');
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

        $result = $trait->callShouldSkipExample();

        expect($result)->toBeFalse();
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

        $result = $trait->callShouldSkipExample();

        expect($result)->toBeTrue();
    });
});

describe('parseTestMethodAnnotations', function () {
    beforeEach(function () {
        $this->trait = new class {
            use ScribeTddSetup;
        };
    });

    it('returns annotations for class when no method given', function () {
        $result = $this->trait::parseTestMethodAnnotations(get_class($this->trait));

        expect($result)->toHaveKey('class');
        expect($result['method'])->toBeNull();
    });

    it('returns method annotations when method exists', function () {
        $trait = new class {
            use ScribeTddSetup;

            /** @scribeDescription Test description */
            public function testExample()
            {
            }
        };

        $result = $trait::parseTestMethodAnnotations(get_class($trait), 'testExample');

        expect($result)->toHaveKey('method');
        expect($result)->toHaveKey('class');
    });

    it('handles nonexistent method gracefully', function () {
        $result = $this->trait::parseTestMethodAnnotations(get_class($this->trait), 'nonExistentMethod');

        expect($result)->toHaveKey('class');
    });
});

describe('setUpScribeTdd', function () {
    beforeEach(function () {
        Config::set('scribe-tdd.enabled', true);

        $this->trait = new class {
            use ScribeTddSetup;

            public $app;
            public $afterCb;
            public $beforeCb;

            public function callSetUp(): void
            {
                $this->setUpScribeTdd();
            }

            public static function setShutdownRegistered(bool $value): void
            {
                self::$shutdownRegistered = $value;
            }

            public static function getShutdownRegistered(): bool
            {
                return self::$shutdownRegistered;
            }

            public function triggerScribeGeneration(): void
            {
            }

            public function afterApplicationCreated(callable $callback)
            {
                $this->afterCb = $callback;
            }

            public function beforeApplicationDestroyed(callable $callback)
            {
                $this->beforeCb = $callback;
            }

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
        $this->trait->app = app();
    });

    it('returns early when scribe-tdd is disabled', function () {
        Config::set('scribe-tdd.enabled', false);
        $this->trait::setShutdownRegistered(true);

        $this->trait->callSetUp();

        expect(ExampleCreator::$currentInstance)->toBeNull();

        Config::set('scribe-tdd.enabled', true);
    });

    it('registers callbacks when enabled', function () {
        $this->trait::setShutdownRegistered(true);

        $this->trait->callSetUp();

        expect($this->trait->afterCb)->toBeCallable();
        expect($this->trait->beforeCb)->toBeCallable();

        ($this->trait->afterCb)();
        expect(ExampleCreator::$currentInstance)->not->toBeNull();
        expect(ExampleCreator::$currentInstance->testMethod)->toBe('test_example_method');

        ($this->trait->beforeCb)();
        expect(ExampleCreator::getInstances())->toBeEmpty();
    });

    it('registers shutdown function when not already registered', function () {
        unset($_SERVER['LARAVEL_PARALLEL_TESTING']);
        $this->trait::setShutdownRegistered(false);

        $this->trait->callSetUp();

        expect($this->trait::getShutdownRegistered())->toBeTrue();
    });

    it('throws LaravelNotPresent when app is empty', function () {
        $this->trait->app = null;

        expect(fn() => $this->trait->callSetUp())->toThrow(LaravelNotPresent::class);
    });
});

describe('writeExample', function () {
    it('writes one complete example file to storage', function () {
        $route = new Route(['POST'], 'write-test', fn() => null);
        $route->bind(Request::create('/write-test'));
        File::deleteDirectory(ExampleCreator::writeDir($route));

        $test = Mockery::mock(TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_write',
            'dataName' => '',
            'providedData' => [30.0],
            'description' => 'test write',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = Request::create('/write-test', 'POST', ['name' => 'test']);
        $request->setRouteResolver(fn() => $route);
        $response = new Response('{"ok":true}', 200);

        $exampleRequest = new ExampleRequest($request, $response, $instance);
        $instance->addExampleRequest($exampleRequest);

        $trait = new class {
            use ScribeTddSetup;
        };
        $reflection = new ReflectionMethod($trait, 'writeExample');
        $reflection->invoke($trait);

        $dir = ExampleCreator::writeDir($route);
        $writtenData = json_decode(File::get($instance->writePath()), true);

        expect(File::files($dir))
            ->toHaveCount(1)
            ->and($writtenData)
            ->toEqual($instance->toArray())
            ->toHaveKeys(['url_params', 'query_params', 'body_params', 'responses']);

        $response->headers->set('Date', 'Wed, 29 Jul 2026 20:30:38 GMT');
        $response->setContent('{"ok":false}');
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);
        $reflection->invoke($trait);

        expect(json_decode(File::get($instance->writePath()), true))->toBe($writtenData);

        File::deleteDirectory($dir);
    });

    it('rewrites an example when its structure changes', function () {
        $route = new Route(['POST'], 'write-structure-test', fn() => null);
        $route->bind(Request::create('/write-structure-test'));
        File::deleteDirectory(ExampleCreator::writeDir($route));

        $test = Mockery::mock(TestCase::class);
        $instance = new ExampleCreator([
            'test' => $test,
            'testMethod' => 'test_write_structure',
            'dataName' => '',
            'providedData' => [],
            'description' => 'test write structure',
        ]);
        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);

        $request = Request::create('/write-structure-test', 'POST');
        $request->setRouteResolver(fn() => $route);
        $instance->addExampleRequest(new ExampleRequest($request, new Response('{"id":1}', 200), $instance));

        $trait = new class {
            use ScribeTddSetup;
        };
        $reflection = new ReflectionMethod($trait, 'writeExample');
        $reflection->invoke($trait);

        $initialData = json_decode(File::get($instance->writePath()), true);
        $initialData['responses'][0]['content'] = '{"id":1,"name":"Product"}';
        File::put($instance->writePath(), json_encode($initialData, JSON_PRETTY_PRINT));

        ExampleCreator::setCurrentInstance($instance);
        ExampleCreator::getInstanceForRoute($route);
        $reflection->invoke($trait);

        expect(json_decode(File::get($instance->writePath()), true)['responses'][0]['content'])
            ->toBe('{"id":1}');

        File::deleteDirectory(ExampleCreator::writeDir($route));
    });
});

describe('triggerScribeGeneration', function () {
    it('creates application and sets server vars before scribe:generate', function () {
        $trait = new class {
            use ScribeTddSetup;

            public bool $applicationCreated = false;

            public function createApplication()
            {
                $this->applicationCreated = true;
                return app();
            }
        };

        try {
            $trait->triggerScribeGeneration();
        } catch (Throwable) {
            // scribe:generate may fail in test env - that's fine,
            // we just need to verify the setup lines executed
        }

        expect($trait->applicationCreated)->toBeTrue();
        expect($_SERVER['SCRIBE_TESTS'])->toBeTrue();
        expect(ScribeServiceProvider::$customTranslationLayerLoaded)->toBeFalse();

        unset($_SERVER['SCRIBE_TESTS']);
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

        $result = $trait->callGetName();

        expect($result)->toBe('fallback_name');
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

        $result = $trait->callGetProvidedData();

        expect($result)->toBe(['key' => 'value']);
    });
});
