<?php

declare(strict_types=1);

namespace MediTrack\Core;

class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $instances = [];

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?Container $container): void
    {
        self::$instance = $container;
    }

    public function bind(string $abstract, mixed $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        // Flag as singleton by recording in bindings array with wrapper or tracking
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => true,
        ];
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            if (class_exists($abstract)) {
                return $this->build($abstract);
            }
            throw new \RuntimeException("Container: Service [{$abstract}] not found.");
        }

        $binding = $this->bindings[$abstract];

        if (is_array($binding) && isset($binding['singleton']) && $binding['singleton'] === true) {
            $concrete = $binding['concrete'];
            $object = $this->build($concrete);
            $this->instances[$abstract] = $object;
            return $object;
        }

        return $this->build($binding);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->build($abstract, $parameters);
    }

    private function build(mixed $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            return $concrete;
        }

        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Container: Class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Container: Cannot resolve parameter [{$name}] for [{$concrete}].");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}

