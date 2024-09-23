<?php

namespace Xtompie\Container;

interface Provider
{
    public static function provide(string $abstract, Container $container): object;
}
