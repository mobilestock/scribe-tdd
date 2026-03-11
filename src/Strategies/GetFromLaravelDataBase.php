<?php

namespace AjCastro\ScribeTdd\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionUnionType;
use Spatie\LaravelData\Data;

class GetFromLaravelDataBase extends Strategy
{
    use ParsesValidationRules;

    protected string $customParameterDataMethodName = '';

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        if (!class_exists(Data::class)) {
            return [];
        }

        return $this->getParametersFromLaravelData($endpointData->method, $endpointData->route);
    }

    protected function getParametersFromLaravelData(ReflectionFunctionAbstract $method, $route): array
    {
        $laravelDataReflectionClass = $this->getLaravelDataReflectionClass($method);

        if (!$laravelDataReflectionClass) {
            return [];
        }

        if (!$this->isLaravelDataMeantForThisStrategy($laravelDataReflectionClass)) {
            return [];
        }

        $className = $laravelDataReflectionClass->getName();

        $rules = $this->getRouteValidationRules($className);

        if (empty($rules)) {
            return [];
        }

        $customParameterData = $this->getCustomParameterData($className);

        $parametersFromLaravelData = $this->getParametersFromValidationRules(
            $this->normalizeDataRules($rules),
            $customParameterData
        );

        return $this->normaliseArrayAndObjectParameters($parametersFromLaravelData);
    }

    protected function getRouteValidationRules(string $className): array
    {
        if (method_exists($className, 'getValidationRules')) {
            return $className::getValidationRules([]);
        }

        return [];
    }

    protected function getCustomParameterData(string $className): array
    {
        if (method_exists($className, $this->customParameterDataMethodName)) {
            return $className::{$this->customParameterDataMethodName}();
        }

        c::debug(
            "No {$this->customParameterDataMethodName}() method found in {$className}. Scribe will only be able to extract basic information from the validation rules."
        );

        return [];
    }

    protected function getMissingCustomDataMessage($parameterName): string
    {
        return "No data found for parameter '{$parameterName}' in your {$this->customParameterDataMethodName}() method. Add an entry for '{$parameterName}' so you can add a description and example.";
    }

    protected function getLaravelDataReflectionClass(ReflectionFunctionAbstract $method): ?ReflectionClass
    {
        foreach ($method->getParameters() as $argument) {
            $argType = $argument->getType();

            if ($argType === null || $argType instanceof ReflectionUnionType) {
                continue;
            }

            $argumentClassName = $argType->getName();

            if (!class_exists($argumentClassName)) {
                continue;
            }

            try {
                $argumentClass = new ReflectionClass($argumentClassName);
            } catch (ReflectionException) {
                continue;
            }

            if ($argumentClass->isSubclassOf(Data::class)) {
                return $argumentClass;
            }
        }

        return null;
    }

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        return true;
    }

    /**
     * Normalize rules returned by laravel-data's getValidationRules()
     * to ensure compatibility with Scribe's ParsesValidationRules.
     *
     * Rules may contain Spatie validation attribute objects that need
     * to be converted to strings or Laravel Rule objects.
     */
    protected function normalizeDataRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $normalized[$field] = $fieldRules;
                continue;
            }

            if (!is_array($fieldRules)) {
                $normalized[$field] = [$fieldRules];
                continue;
            }

            $normalizedFieldRules = [];

            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $normalizedFieldRules[] = $rule;
                } elseif (is_object($rule) && method_exists($rule, '__toString')) {
                    $normalizedFieldRules[] = (string) $rule;
                } else {
                    // Laravel Rule objects, custom rules, etc. - pass through as-is
                    // Scribe's ParsesValidationRules handles these natively
                    $normalizedFieldRules[] = $rule;
                }
            }

            $normalized[$field] = $normalizedFieldRules;
        }

        return $normalized;
    }
}
