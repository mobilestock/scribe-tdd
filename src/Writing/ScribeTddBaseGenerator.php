<?php

namespace AjCastro\ScribeTdd\Writing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
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

    protected function generateEndpointParametersSpec(OutputEndpointData $endpoint): array
    {
        $parameters = parent::generateEndpointParametersSpec($endpoint);

        foreach ($parameters as &$param) {
            if (($param['in'] ?? '') !== 'query') {
                continue;
            }

            $name = $param['name'];
            $details = $endpoint->queryParameters[$name] ?? null;

            if ($details && str_contains($details['type'], '[]')) {
                $param['name'] = $name . '[]';
            }
        }

        return $parameters;
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
                return $this->operationIdFromUri($endpoint);
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

        return $this->operationIdFromUri($endpoint);
    }

    protected function operationIdFromUri(OutputEndpointData $endpoint): string
    {
        $method = strtolower($endpoint->httpMethods[0] ?? 'get');
        $segments = array_filter(explode('/', $endpoint->uri));

        if (empty($segments)) {
            return $method;
        }

        return $method . Str::studly(implode(' ', $segments));
    }
}
