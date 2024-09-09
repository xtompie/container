<?php

namespace Xtompie\Container;

use ReflectionClass;

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

    public function bindings(array $bindings): static
    {
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function instances(array $instances): static
    {
        $this->instances = array_merge($this->instances, $instances);
        return $this;
    }

    public function transient(string $abstract): void
    {
        $this->transient[$abstract] = true;
    }

    public function transients(array $transients): static
    {
        $this->transient = array_merge($this->transient, array_fill_keys($transients, true));
        return $this;
    }

    public function provider(string $abstract, string $provider): void
    {
        $this->providers[$abstract] = $provider;
    }

    public function providers(array $providers): static
    {
        $this->providers = array_merge($this->providers, $providers);
        return $this;
    }

    public function __invoke(string $abstract): object
    {
        return $this->resolve($abstract, []);
    }

    public function get(string $abstract): object
    {
        return $this->resolve($abstract, []);
    }

    public function concrete(string $abstract): string
    {
        return isset($this->bindings[$abstract]) ? $this->concrete($this->bindings[$abstract]) : $abstract;
    }

    public function resolve(string $abstract, array $values): mixed
    {
        $concrete = $this->concrete($abstract);

        $service = $this->resolveUsingInstances($concrete);
        if ($service) {
            return $service;
        }

        $service = $this->resolveUsingProviders($abstract);

        if (!$service) {
            $service = $this->resolveUsingReflections($concrete, $values);
        }

        $this->resolveTransient($concrete, $service);

        return $service;
    }

    protected function resolveUsingInstances(string $concrete): ?object
    {
        return $this->instances[$concrete] ?? null;
    }

    protected function resolveUsingProviders(string $abstract): ?object
    {
        $provider = $this->providers[$abstract] ?? null;
        if ($provider && is_subclass_of($provider, Provider::class)) {
            return $provider::provide($abstract, $this);
        }
        return null;
    }

    protected function resolveUsingReflections(string $concrete, array $values): object
    {
        $class = new ReflectionClass($concrete);
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $arg) {
                if (isset($values[$arg->getName()])) {
                    $args[] = $values[$arg->getName()];
                }
                else if ($arg->isDefaultValueAvailable()) {
                    $args[] = $arg->getDefaultValue();
                }
                else {
                    $args[] = $this->get($arg->getType()->getName(), $concrete);
                }
            }
        }
        return $class->newInstanceArgs($args);
    }

    protected function resolveTransient(string $concrete, object $service): void
    {
        if (isset($this->transient[$concrete])) {
            return;
        }

        if ($service instanceof Transient) {
            return;
        }

        $this->instances[$concrete] = $service;
    }
}
