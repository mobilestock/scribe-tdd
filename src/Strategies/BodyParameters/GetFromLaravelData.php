<?php

namespace AjCastro\ScribeTdd\Strategies\BodyParameters;

use AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase;
use ReflectionClass;

class GetFromLaravelData extends GetFromLaravelDataBase
{
    protected string $customParameterDataMethodName = 'bodyParameters';

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        $docBlock = $laravelDataReflectionClass->getDocComment();

        if ($docBlock && str_contains(mb_strtolower($docBlock), 'query parameters')) {
            return false;
        }

        if (
            $laravelDataReflectionClass->hasMethod('queryParameters') &&
            !$laravelDataReflectionClass->hasMethod('bodyParameters')
        ) {
            return false;
        }

        return true;
    }
}
