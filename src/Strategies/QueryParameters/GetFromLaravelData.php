<?php

namespace AjCastro\ScribeTdd\Strategies\QueryParameters;

use AjCastro\ScribeTdd\Strategies\GetFromLaravelDataBase;
use ReflectionClass;

class GetFromLaravelData extends GetFromLaravelDataBase
{
    protected string $customParameterDataMethodName = 'queryParameters';

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        $docBlock = $laravelDataReflectionClass->getDocComment();

        if ($docBlock && str_contains(mb_strtolower($docBlock), 'query parameters')) {
            return true;
        }

        if ($laravelDataReflectionClass->hasMethod('queryParameters')) {
            return true;
        }

        return false;
    }
}
