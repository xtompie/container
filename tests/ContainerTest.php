<?php

use PHPUnit\Framework\TestCase;
use Xtompie\Container\Container;
use Xtompie\Container\Provider;

class Foo
{
    public function method(): string
    {
        return 'Foo';
    }
}

class Foo2 extends Foo
{
    public function method(): string
    {
        return 'Foo2';
    }
}

interface FooInterface {
    public function method(): string;
}

// Class with dependencies for nested resolution test
class Bar {

    public function __construct(
        public Foo $foo,
        public ?string $qux = null,
    ) {
    }
}

class Baz {
}

class BazProvider implements Provider
{
    public static function provide(string $abstract, Container $container): object
    {
        return new Baz();
    }
}

class ContainerTest extends TestCase
{
    public function testShouldReturnSingletonContainerInstance()
    {
        // given
        $container1 = Container::container();

        // when
        $container2 = Container::container();

        // then
        $this->assertSame($container1, $container2);
    }

    public function testShouldResolveClassUsingReflection()
    {
        // given
        $container = new Container();

        // when
        $result = $container->get(Foo::class);

        // then
        $this->assertInstanceOf(Foo::class, $result);
    }

    public function testShouldResolveClassWithDependenciesUsingReflection()
    {
        // given
        $container = new Container();

        // when
        $result = $container->get(Bar::class);

        // then
        $this->assertInstanceOf(Bar::class, $result);
        $this->assertInstanceOf(Foo::class, $result->foo);
        $this->assertNull($result->qux);
    }

    public function testShouldBindInterfaceToConcreteClass()
    {
        // given
        $container = new Container();
        $container->bind(FooInterface::class, Foo::class);

        // when
        $result = $container->get(FooInterface::class);

        // then
        $this->assertInstanceOf(Foo::class, $result);
    }

    public function testShouldAliasClassBinding()
    {
        // given
        $container = new Container();
        $container->bind(Foo::class, Foo2::class);

        // when
        $result = $container->get(Foo::class);

        // then
        $this->assertInstanceOf(Foo2::class, $result);
    }

    public function testShouldReturnSameInstanceForSharedService()
    {
        // given
        $container = new Container();

        // when
        $instance1 = $container->get(Foo::class);
        $instance2 = $container->get(Foo::class);

        // then
        $this->assertSame($instance1, $instance2);
    }

    public function testShouldResolveServiceUsingProvider()
    {
        // given
        $container = new Container();
        $container->provider('Quux', BazProvider::class);

        // when
        $result = $container->get('Quux');

        // then
        $this->assertInstanceOf(Baz::class, $result);
    }

    public function testShouldResolveMultiBindingToMostConcreteClass()
    {
        // given
        $container = new Container();
        $container->bind(FooInterface::class, Foo::class);
        $container->bind(Foo::class, Foo2::class);

        // when
        $result = $container->get(FooInterface::class);

        // then
        $this->assertInstanceOf(Foo2::class, $result);
    }

    public function testShouldReturnDifferentInstancesForTransientService()
    {
        // given
        $container = new Container();
        $container->transient(Foo::class);

        // when
        $instance1 = $container->get(Foo::class);
        $instance2 = $container->get(Foo::class);

        // then
        $this->assertNotSame($instance1, $instance2);
    }

    public function testResolveInjectsProvidedValues()
    {
        // given
        $container = new Container();
        $values = ['qux' => 'quxx'];

        // when
        $result = $container->resolve(Bar::class, $values);

        // then
        $this->assertEquals('quxx', $result->qux);
    }

    public function testResolveReturnsNewInstanceEachTime()
    {
        // given
        $container = new Container();
        $values1 = ['qux' => 'quxx'];
        $values2 = ['qux' => 'quxy'];

        // when
        $result1 = $container->resolve(Bar::class, $values1);
        $result2 = $container->resolve(Bar::class, $values2);

        // then
        $this->assertNotSame($result1, $result2); // Ensure different instances
        $this->assertEquals('quxx', $result1->qux);
        $this->assertEquals('quxy', $result2->qux);
    }
}