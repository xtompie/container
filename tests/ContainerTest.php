<?php

use PHPUnit\Framework\TestCase;
use Xtompie\Container\Container;
use Xtompie\Container\Provider;

class Call
{
    public static function f1(Foo $foo): string
    {
        return $foo->method();
    }

    public function f2(Foo $foo): string
    {
        return $foo->method();
    }
}

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

class Qux implements Provider
{
    public static function provide(string $abstract, Container $container): object
    {
        return new Qux(val: 42);
    }

    public function __construct(
        private int $val,
    ) {
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

    public function testCallWithClosure()
    {
        // given
        $container = new Container();

        // when
        $result = $container->call(function(Foo $foo) {
            return $foo->method();
        });

        // then
        $this->assertEquals('Foo', $result);
    }

    public function testCallWithStaticClassMethod()
    {
        // given
        $container = new Container();

        // when
        $result = $container->call([Call::class, 'f1']);

        // then
        $this->assertEquals('Foo', $result);
    }

    public function testCallWithObjectMethod()
    {
        // given
        $container = new Container();
        $call = new Call();

        // when
        $result = $container->call([$call, 'f2']);

        // then
        $this->assertEquals('Foo', $result);
    }

    public function testShouldResolveClassUsingProviderFromAttribute()
    {
        // given
        $container = new Container();

        // when
        $result = $container->get(Qux::class);

        // then
        $this->assertInstanceOf(Qux::class, $result);
    }

    public function testContainerProviderOverridesProviderFromAttribute()
    {
        // given
        $container = new Container();
        $container->provider(Qux::class, BazProvider::class);

        // when
        $result = $container->get(Qux::class);

        // then
        $this->assertInstanceOf(Baz::class, $result);
    }

    public function testCallWithCustomValues()
    {
        // given
        $container = new Container();
        $customValues = ['foo' => new Foo2()];

        // when
        $result = $container->call(function(Foo $foo) {
            return $foo->method();
        }, $customValues);

        // then
        $this->assertEquals('Foo2', $result);
    }

    public function testCallWithStaticClassMethodAndCustomValues()
    {
        // given
        $container = new Container();
        $customValues = ['foo' => new Foo2()];

        // when
        $result = $container->call([Call::class, 'f1'], $customValues);

        // then
        $this->assertEquals('Foo2', $result);
    }

    public function testCallWithObjectMethodAndCustomValues()
    {
        // given
        $container = new Container();
        $call = new Call();
        $customValues = ['foo' => new Foo2()];

        // when
        $result = $container->call([$call, 'f2'], $customValues);

        // then
        $this->assertEquals('Foo2', $result);
    }

    public function testCallShouldOverrideContainerResolvedValueWithCustomValue()
    {
        // given
        $container = new Container();
        // Container would normally resolve Foo, but we override it with Foo2
        $customValues = ['foo' => new Foo2()];

        // when
        $result = $container->call(function(Foo $foo) {
            return $foo->method();
        }, $customValues);

        // then
        // Foo2 should take precedence over the Container-resolved Foo
        $this->assertEquals('Foo2', $result);
    }

    public function testCallArgsWithCustomArgumentResolverReturnsResolvedValue()
    {
        // given
        $container = new Container();
        $customResolver = function (string $abstract, string $name) {
            if ($abstract === Foo::class) {
                return new Foo2();
            }
            return null;
        };

        // when
        $args = $container->callArgs(function(Foo $foo) {
            return $foo->method();
        }, [], $customResolver);

        // then
        $this->assertEquals('Foo2', $args['foo']->method());
    }

    public function testCallArgsWithCustomArgumentResolverFallsBackToContainerWhenNull()
    {
        // given
        $container = new Container();
        $customResolver = function (string $abstract, string $name) {
            return null;
        };

        // when
        $args = $container->callArgs(function(Foo $foo) {
            return $foo->method();
        }, [], $customResolver);

        // then
        $this->assertEquals('Foo', $args['foo']->method());
    }

    public function testCallArgsWithCustomArgumentResolverOverridesCustomValues()
    {
        // given
        $container = new Container();
        $customResolver = function (string $abstract, string $name) {
            if ($abstract === Foo::class) {
                return new Foo2();
            }
            return null;
        };
        $customValues = ['foo' => new Foo()];

        // when
        $args = $container->callArgs(function(Foo $foo) {
            return $foo->method();
        }, $customValues, $customResolver);

        // then
        $this->assertEquals('Foo2', $args['foo']->method());
    }
}