<?php

namespace Xtompie\Container;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/** @phpstan-consistent-constructor */
class Container
{
    protected static Container $container;

    /**
     * @var array<class-string, class-string>
     */
    protected array $bindings = [];

    /**
     * @var array<class-string, bool>
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
        return static::$container ??= new static();
    }

    public function __construct()
    {
    }

    public function setContainer(self $container): void
    {
        static::$container = $container;
    }

    /**
     * @param class-string $abstract
     * @param class-string $concrete
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * @param class-string $concrete
     * @param object $instance
     */
    public function instance(string $concrete, object $instance): void
    {
        $this->instances[$concrete] = $instance;
    }

    /**
     * @param class-string $concrete
     */
    public function transient(string $concrete): void
    {
        $this->transient[$concrete] = true;
    }

    /**
     * @param class-string $abstract
     * @param class-string $provider
     */
    public function provider(string $abstract, string $provider): void
    {
        $this->providers[$abstract] = $provider;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function __invoke(string $abstract)
    {
        return $this->solve($abstract, null);
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function get(string $abstract)
    {
        return $this->solve($abstract, null);
    }

    /**
     * @param class-string $abstract
     * @return class-string
     */
    public function concrete(string $abstract): string
    {
        return isset($this->bindings[$abstract]) ? $this->concrete($this->bindings[$abstract]) : $abstract;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param array<string, mixed> $values
     * @return T
     */
    public function resolve(string $abstract, array $values)
    {
        return $this->solve($abstract, $values);
    }

    /**
     * @param Closure|array{0: object|string, 1: string}|string $callback
     * @param array<string, mixed> $values
     * @param Closure|null $arg
     * @return mixed
     */
    public function call(Closure|array|string $callback, array $values = [], ?Closure $arg = null): mixed
    {
        if (is_string($callback)) {
            $reflection = new ReflectionMethod($callback);
            $args = $this->solveArgs($reflection->getParameters(), $values, $arg);
            return $reflection->invokeArgs(null, $args);
        } elseif (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
            $args = $this->solveArgs($reflection->getParameters(), $values, $arg);
            return $reflection->invokeArgs(is_object($callback[0]) ? $callback[0] : null, $args);
        } elseif (is_callable($callback)) {
            $reflection = new ReflectionFunction($callback);
            $args = $this->solveArgs($reflection->getParameters(), $values, $arg);
            return $reflection->invokeArgs($args);
        } else {
            throw new Exception('Invalid callback type.');
        }
    }

    /**
     * @param Closure|array{0: object|string, 1: string}|string $callback
     * @param array<string, mixed> $values
     * @param Closure|null $arg
     * @return array<string, mixed>
     */
    public function callArgs(Closure|array|string $callback, array $values = [], ?Closure $arg = null): array
    {
        if (is_string($callback)) {
            $reflection = new ReflectionMethod($callback);
        } elseif (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_callable($callback)) {
            $reflection = new ReflectionFunction($callback);
        } else {
            throw new Exception('Invalid callback type.');
        }

        return $this->solveArgs($reflection->getParameters(), $values, $arg);
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param array<string, mixed>|null $values
     * @return T
     */
    protected function solve(string $abstract, ?array $values)
    {
        $concrete = $this->concrete($abstract);

        $service = $this->solveInstance($concrete);
        if ($service) {
            /** @var T $service */
            return $service;
        }

        $service = $this->solveProvider($concrete);

        if (!$service) {
            $service = $this->solveReflection($concrete, $values);
        }

        $this->solveTransient($concrete, $service, $values);

        /** @var T $service */
        return $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $concrete
     * @return ?T
     */
    protected function solveInstance(string $concrete)
    {
        /** @var T|null $instance */
        $instance = $this->instances[$concrete] ?? null;
        return $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return ?T
     */
    protected function solveProvider(string $abstract)
    {
        $provider = $this->providers[$abstract] ?? null;
        if ($provider && is_subclass_of($provider, Provider::class)) {
            /** @var T */
            return $provider::provide($abstract, $this);
        }

        if (is_subclass_of($abstract, Provider::class)) {
            /** @var T */
            return $abstract::provide($abstract, $this);
        }

        return null;
    }

    /**
     * @template T of object
     * @param class-string<T> $concrete
     * @param array<string, mixed>|null $values
     * @return T
     */
    protected function solveReflection(string $concrete, ?array $values)
    {
        $class = new ReflectionClass($concrete);
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor) {
            $args = $this->solveArgs($constructor->getParameters(), $values);
        }
        /** @var T */
        return $class->newInstanceArgs($args);
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @param array<string, mixed>|null $values
     * @param callable(ReflectionParameter):mixed|null $arg
     * @return array<string, mixed>
     */
    protected function solveArgs(array $parameters, ?array $values, ?callable $arg = null): array
    {
        $args = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $abstract = null;
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $abstract = $type->getName();
            }
            $name = $parameter->getName();

            if ($arg) {
                $resolved = $arg($parameter);
                if ($resolved !== null) {
                    $args[$name] = $resolved;
                    continue;
                }
            }

            if ($values !== null && array_key_exists($name, $values)) {
                $args[$name] = $values[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[$name] = $parameter->getDefaultValue();
            } elseif ($abstract !== null) {
                /** @var class-string */
                $className = $abstract;
                $args[$name] = $this->get($className);
            } else {
                throw new Exception("Cannot resolve parameter '$name'");
            }
        }

        return $args;
    }

    /**
     * @param class-string $concrete
     * @param object $service
     * @param array<string, mixed>|null $values
     */
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
