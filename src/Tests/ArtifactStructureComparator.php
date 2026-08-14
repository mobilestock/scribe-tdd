<?php

namespace AjCastro\ScribeTdd\Tests;

class ArtifactStructureComparator
{
    public function areCompatible(array $existing, array $generated): bool
    {
        return $this->structuresAreCompatible(
            $this->comparisonStructure($existing),
            $this->comparisonStructure($generated)
        );
    }

    private function comparisonStructure(array $data): array
    {
        return [
            'description' => $data['description'] ?? null,
            'url_params' => $this->parameterStructure($data['url_params'] ?? []),
            'query_params' => $this->parameterStructure($data['query_params'] ?? []),
            'body_params' => $this->parameterStructure($data['body_params'] ?? []),
            'responses' => array_map(
                fn(array $response) => [
                    'status' => $response['status'] ?? null,
                    'description' => $response['description'] ?? null,
                    'content' => $this->contentStructure($response['content'] ?? null),
                ],
                $data['responses'] ?? []
            ),
        ];
    }

    private function parameterStructure(array $parameters): array
    {
        return array_map(function (mixed $parameter): mixed {
            if (is_array($parameter) && array_key_exists('example', $parameter)) {
                $parameter['example'] = $this->valueShape($parameter['example']);

                return $parameter;
            }

            return $this->valueShape($parameter);
        }, $parameters);
    }

    private function contentStructure(mixed $content): mixed
    {
        if (!is_string($content)) {
            return $this->valueShape($content);
        }

        $decodedContent = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $this->valueShape($decodedContent) : get_debug_type($content);
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

        $shape = [];

        foreach ($value as $key => $item) {
            $shape[is_int($key) ? 'number' : $key] = $this->valueShape($item);
        }

        return $shape;
    }

    private function structuresAreCompatible(mixed $existing, mixed $generated): bool
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
            return $this->listShapesAreCompatible($existing, $generated) &&
                $this->listShapesAreCompatible($generated, $existing);
        }

        if (array_keys($existing) !== array_keys($generated)) {
            return false;
        }

        foreach ($existing as $key => $value) {
            if (!$this->structuresAreCompatible($value, $generated[$key])) {
                return false;
            }
        }

        return true;
    }

    private function listShapesAreCompatible(array $shapes, array $candidates): bool
    {
        foreach ($shapes as $shape) {
            foreach ($candidates as $candidate) {
                if ($this->structuresAreCompatible($shape, $candidate)) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }
}
