<?php

namespace Tests;

use AjCastro\ScribeTdd\ScribeTddServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app)
    {
        return [ScribeTddServiceProvider::class];
    }
}
