# Container

The **Container** class provides essential features for managing dependencies and class instances in a PHP application. It allows you to:

- Retrieve a single, shared container instance (singleton pattern).
- Automatically resolve class instances using reflection, including handling constructor dependencies.
- Bind interfaces to concrete classes for dependency injection.
- Alias one class to another, allowing different class implementations to be used interchangeably.
- Manage shared (singleton) instances, ensuring that the same instance is returned for multiple usages.
- Use service providers to control how specific services are resolved.
- Configure transient services, which return different instances each time they are accessed.

## Requirements

- PHP >= 8

## Installation

Using [composer](https://getcomposer.org)

```shell
composer require xtompie/container
```

## Usage

### Singleton

The **Container** class implements the singleton pattern, ensuring that the same instance of the container is used throughout the application. This is useful for managing global services and dependencies.

```php
use Xtompie\Container\Container;

$container1 = Container::container();
$container2 = Container::container();

var_dump($container1 === $container2); // true
```

### Resolving

The **Container** automatically resolves class instances using PHP's reflection capabilities. It inspects the class constructor to determine required dependencies and resolves them automatically.

```php
use Xtompie\Container\Container;

class Foo
{
    // Foo class logic
}

$container = new Container();
$foo = $container->get(Foo::class);

var_dump($foo instanceof Foo); // true
```

### Dependencies

If a class requires dependencies in its constructor, the **Container** resolves them as well. It inspects the constructor and creates instances of required classes.

```php
use Xtompie\Container\Container;

class Bar
{
    public function __construct(
        public Foo $foo,
        public ?string $qux = null
    ) {
    }
}

$container = new Container();
$bar = $container->get(Bar::class);

var_dump($bar instanceof Bar); // true
var_dump($bar->foo instanceof Foo); // true
var_dump($bar->qux); // null
```

### Binding

You can bind an interface to a concrete class, allowing the container to resolve the correct implementation when the interface is requested.

```php
use Xtompie\Container\Container;

interface FooInterface {}

class Foo implements FooInterface {}

$container = new Container();
$container->bind(FooInterface::class, Foo::class);

$fooInterface = $container->get(FooInterface::class);
var_dump($fooInterface instanceof Foo); // true
```

### Alias

The container allows you to alias one class to another, making it possible to use different class implementations interchangeably.

```php
use Xtompie\Container\Container;

class Foo2 {}

$container = new Container();
$container->bind(Foo::class, Foo2::class);

$foo = $container->get(Foo::class);
var_dump($foo instanceof Foo2); // true
```

### Shared

The container manages shared services, ensuring the same instance is returned each time the service is retrieved. This is useful for managing singletons.

```php
use Xtompie\Container\Container;

$container = new Container();

$instance1 = $container->get(Foo::class);
$instance2 = $container->get(Foo::class);

var_dump($instance1 === $instance2); // true
```

### Transient

Transient services return a new instance each time they are accessed. By marking a class as transient, the **Container** ensures that different instances are returned on every access.

You can either define a service as transient manually using the `transient()` method or implement the `Transient` interface to automatically mark a class as transient.

```php
use Xtompie\Container\Container;

$container = new Container();
$container->transient(Foo::class);

$instance1 = $container->get(Foo::class);
$instance2 = $container->get(Foo::class);

var_dump($instance1 === $instance2); // false
```

Alternatively, if a service class implements the `Transient` interface, the **Container** will automatically treat it as transient, meaning a new instance is returned on each retrieval:

```php
use Xtompie\Container\Container;
use Xtompie\Container\Transient;

class Foo implements Transient {}

$container = new Container();
$instance1 = $container->get(Foo::class);
$instance2 = $container->get(Foo::class);

var_dump($instance1 === $instance2); // false
```

In both cases, this ensures that transient objects are always new instances, as opposed to shared services, which return the same object every time.

### Provider

Providers can be used for more complex service resolution. A provider is a class that implements the `Provider` interface, which allows you to control how specific services are created or managed within the container. Providers inject additional logic into the resolution process.

Here's the `Provider` interface:

```php
namespace Xtompie\Container;

interface Provider
{
    public static function provide(string $abstract, Container $container): mixed;
}
```

To use a provider, first create a class that implements the `Provider` interface. The `provide()` method should return the service instance, and it will be invoked by the container when needed.

Example of a provider class:

```php
use Xtompie\Container\Container;
use Xtompie\Container\Provider;

class Baz {}

class BazProvider implements Provider
{
    public static function provide(string $abstract, Container $container): mixed
    {
        return new Baz();
    }
}
```

You can then register the provider with the container and retrieve the service:

```php
use Xtompie\Container\Container;

$container = new Container();
$container->provider('Quux', BazProvider::class);

$quux = $container->get('Quux');
var_dump($quux instanceof Baz); // true
```

This approach allows for more complex or conditional logic when resolving services within the container.

### Multi-binding

If you bind multiple classes, the container resolves the most concrete class available. This is useful for managing multiple levels of abstraction or class inheritance.

```php
use Xtompie\Container\Container;

$container = new Container();
$container->bind(FooInterface::class, Foo::class);
$container->bind(Foo::class, Foo2::class);

$fooInterface = $container->get(FooInterface::class);
var_dump($fooInterface instanceof Foo2); // true
```

## Extending

You can extend the **Container** class to add custom functionality or specific behavior for your application.

```php
use Xtompie\Container\Container;

class CustomContainer extends Container
{
    // Custom methods or properties
}
```

This allows you to tailor the container to meet the specific needs of your project, adding custom logic or behavior to the dependency resolution process.
