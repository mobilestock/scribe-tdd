<?php

namespace AjCastro\ScribeTdd\Tests\ParamParsers;

use Illuminate\Support\Arr;

class BodyParamParser
{
    public static function parse(array $value): array
    {
        $processArray = function (array $arrayBodyParam, string $prefix = '') use (&$processArray): array {
            $result = [];
            foreach ($arrayBodyParam as $key => $value) {
                if (is_scalar($value)) {
                    $result[$prefix . $key] = [
                        'type' => gettype($value),
                        'description' => '',
                        'example' => $value,
                    ];
                } elseif (is_array($value) && !Arr::isAssoc($value)) {
                    $result[$prefix . $key] = [
                        'type' => gettype(head($value)) . '[]',
                        'description' => '',
                        'example' => $value,
                    ];
                } elseif (is_array($value)) {
                    $result += $processArray($value, "$prefix$key.");
                }
            }
        
        return $result;
        };

        return $processArray($value);
    }
}
