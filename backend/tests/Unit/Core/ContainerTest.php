<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;

class DummyDependency
{
    public string $name = 'dependency';
}

class DummyService
{
    public DummyDependency $dep;
    public function __construct(DummyDependency $dep)
    {
        $this->dep = $dep;
    }
}

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        Container::setInstance($this->container);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('stdClass', function () {
            $obj = new \stdClass();
            $obj->random = rand();
            return $obj;
        });

        $first = $this->container->get('stdClass');
        $second = $this->container->get('stdClass');

        $this->assertSame($first, $second);
    }

    public function testAutoWiringResolvesDependencies(): void
    {
        $service = $this->container->get(DummyService::class);
        $this->assertInstanceOf(DummyService::class, $service);
        $this->assertInstanceOf(DummyDependency::class, $service->dep);
    }

    public function testInstanceRegistration(): void
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $this->container->instance('my_instance', $obj);

        $this->assertTrue($this->container->has('my_instance'));
        $this->assertSame($obj, $this->container->get('my_instance'));
    }
}

