<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\BaseGenerator;

class ScribeTddBaseGenerator extends BaseGenerator
{
    protected function generateResponseContentSpec(?string $responseContent, OutputEndpointData $endpoint)
    {
        // Flatten array-valued headers to strings before generating spec,
        // since scribe-tdd stores headers in PSR-7 format (arrays)
        foreach ($endpoint->responses as $response) {
            foreach ($response->headers as $key => $value) {
                if (is_array($value)) {
                    $response->headers[$key] = $value[0] ?? '';
                }
            }
        }

        return parent::generateResponseContentSpec($responseContent, $endpoint);
    }

    protected function queryParamToOpenApiParameterObject(string $name, Parameter $details): array
    {
        $parameterData = parent::queryParamToOpenApiParameterObject($name, $details);

        if (str_contains($details['type'], '[]')) {
            $parameterData['name'] = $name . '[]';
        }

        return $parameterData;
    }

    public function generateSchemaForResponseValue(mixed $value, OutputEndpointData $endpoint, string $path): array
    {
        $response = parent::generateSchemaForResponseValue($value, $endpoint, $path);

        if (($response['type'] ?? null) === 'integer' && is_int($value) && $value > 2 ** 32) {
            $response['format'] = 'int64';
        }

        return $response;
    }

    protected function operationId(OutputEndpointData $endpoint): string
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() !== $endpoint->uri || empty(array_intersect($endpoint->httpMethods, $route->methods()))) {
                continue;
            }

            $uses = $route->getAction()['uses'] ?? null;
            if (!is_string($uses) || !str_contains($uses, '@')) {
                continue;
            }

            $action = explode('@', $uses);
            $controllerName = last(explode('\\', $action[0]));
            $controllerName = Str::endsWith($controllerName, 'Controller')
                ? Str::beforeLast($controllerName, 'Controller')
                : $controllerName;
            $formattedAction = $controllerName . Str::ucfirst($action[1]);
            if (Str::contains($formattedAction, 'Batching')) {
                $tableName = last(explode('/', $endpoint->uri));
                $modelName = Str::studly($tableName);
                $formattedAction .= $modelName;
            }

            return $formattedAction;
        }

        return parent::operationId($endpoint);
    }
}
