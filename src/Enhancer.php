<?php

namespace Xtompie\Container;

interface Enhancer
{
    public static function enhance(object $service, Container $container): mixed;
}