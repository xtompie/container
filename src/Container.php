<?php

namespace Xtompie\Container;

use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class Container
{
    protected static $container;

    /**
     * @var array<class-string, class-string>
     */
    protected array $bindings = [];

    /**
     * @var array<string>
     */
    protected array $transient = [];

    /**
     * @var array<class-string, object>
     */
    protected array $instances = [];

    /**
     * @var array<class-string, class-string>
     */
    protected array $providers = [];

    public static function container(): self
    {
        return static::$container ??= new static;
    }

    public function setContainer(self $container): void
    {
        static::$container = $container;
    }

    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function transient(string $abstract): void
    {
        $this->transient[$abstract] = true;
    }

    public function provider(string $abstract, string $provider): void
    {
        $this->providers[$abstract] = $provider;
    }

    public function __invoke(string $abstract): object
    {
        return $this->solve($abstract, null);
    }

    public function get(string $abstract): object
    {
        return $this->solve($abstract, null);
    }

    public function concrete(string $abstract): string
    {
        return isset($this->bindings[$abstract]) ? $this->concrete($this->bindings[$abstract]) : $abstract;
    }

    public function resolve(string $abstract, array $values): mixed
    {
        return $this->solve($abstract, $values);
    }

    public function call(callable|array|string $callback): mixed
    {
        if (is_string($callback)) {
            $reflection = new ReflectionMethod($callback);
            $args = $this->solveArgs($reflection->getParameters(), null);
            return $reflection->invokeArgs(null, $args);
        }
        elseif (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
            $args = $this->solveArgs($reflection->getParameters(), null);
            return $reflection->invokeArgs(is_object($callback[0]) ? $callback[0] : null, $args);
        }
        elseif (is_callable($callback)) {
            $reflection = new ReflectionFunction($callback);
            $args = $this->solveArgs($reflection->getParameters(), null);
            return $reflection->invokeArgs($args);
        }
        else {
            throw new Exception("Invalid callback type.");
        }
    }

    protected function solve(string $abstract, ?array $values): mixed
    {
        $concrete = $this->concrete($abstract);

        $service = $this->solveInstance($concrete);
        if ($service) {
            return $service;
        }

        $service = $this->solveProvider($concrete);

        if (!$service) {
            $service = $this->solveReflection($concrete, $values);
        }

        $this->solveTransient($concrete, $service, $values);

        return $service;
    }

    protected function solveInstance(string $concrete): ?object
    {
        return $this->instances[$concrete] ?? null;
    }

    protected function solveProvider(string $abstract): ?object
    {
        $provider = $this->providers[$abstract] ?? null;
        if ($provider && is_subclass_of($provider, Provider::class)) {
            return $provider::provide($abstract, $this);
        }
        return null;
    }

    protected function solveReflection(string $concrete, ?array $values): object
    {
        $class = new ReflectionClass($concrete);
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor) {
            $args = $this->solveArgs($constructor->getParameters(), $values);
        }
        return $class->newInstanceArgs($args);
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @param array|null $values
     * @return array
     */
    protected function solveArgs(array $parameters, ?array $values): array
    {
        $args = [];
        foreach ($parameters as $arg) {
            if (isset($values, $values[$arg->getName()])) {
                $args[] = $values[$arg->getName()];
            }
            else if ($arg->isDefaultValueAvailable()) {
                $args[] = $arg->getDefaultValue();
            }
            else {
                $args[] = $this->get($arg->getType()->getName());
            }
        }
        return $args;
    }

    protected function solveTransient(string $concrete, object $service, ?array $values): void
    {
        if (isset($this->transient[$concrete])) {
            return;
        }

        if ($service instanceof Transient) {
            return;
        }

        if ($values !== null) {
            return;
        }

        $this->instances[$concrete] = $service;
    }
}
