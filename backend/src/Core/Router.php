<?php

declare(strict_types=1);

namespace MediTrack\Core;

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\HttpException;

class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private array $globalMiddleware = [];

    public function addGlobalMiddleware(string $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $uri, $action, $middleware);
    }

    public function patch(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $uri, $action, $middleware);
    }

    public function delete(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $uri, $action, $middleware);
    }

    public function options(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $uri, $action, $middleware);
    }

    public function addRoute(string $httpMethod, string $uri, mixed $action, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array)$group['middleware']);
            }
        }

        $fullUri = $prefix . '/' . trim($uri, '/');
        if ($fullUri === '') {
            $fullUri = '/';
        }
        if ($fullUri !== '/' && str_ends_with($fullUri, '/')) {
            $fullUri = rtrim($fullUri, '/');
        }

        $allMiddleware = array_merge($groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($httpMethod),
            'uri' => $fullUri,
            'action' => $action,
            'middleware' => $allMiddleware,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $index => $route) {
                $r->addRoute($route['method'], $route['uri'], $index);
            }
        });

        $httpMethod = $request->getMethod();
        $uri = $request->getPath();

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                throw new NotFoundException("Route not found: {$httpMethod} {$uri}");

            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new HttpException(
                    405,
                    'Method Not Allowed. Supported methods: ' . implode(', ', $allowedMethods),
                    'METHOD_NOT_ALLOWED',
                    ['Allow' => implode(', ', $allowedMethods)]
                );

            case \FastRoute\Dispatcher::FOUND:
                $routeIndex = $routeInfo[1];
                $vars = $routeInfo[2];
                $route = $this->routes[$routeIndex];

                // Attach matched URL parameters to Request attributes
                foreach ($vars as $key => $val) {
                    $request = $request->withAttribute($key, $val);
                }

                $allMiddleware = array_merge($this->globalMiddleware, $route['middleware']);
                return $this->runMiddlewarePipeline($allMiddleware, $request, function (Request $req) use ($route, $vars) {
                    return $this->runAction($route['action'], $req, $vars);
                });
        }

        throw new NotFoundException('Route could not be resolved');
    }

    private function runMiddlewarePipeline(array $middlewareStack, Request $request, callable $destination): Response
    {
        $container = Container::getInstance();

        $runner = array_reduce(
            array_reverse($middlewareStack),
            function ($next, $middlewareItem) use ($container) {
                return function (Request $req) use ($next, $middlewareItem, $container) {
                    $middleware = is_object($middlewareItem) ? $middlewareItem : $container->get($middlewareItem);
                    return $middleware->process($req, $next);
                };
            },
            $destination
        );

        return $runner($request);
    }

    private function runAction(mixed $action, Request $request, array $vars): Response
    {
        $container = Container::getInstance();

        if (is_callable($action)) {
            $result = $action($request, ...array_values($vars));
        } elseif (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = $container->get($class);
            $result = $controller->{$method}($request, ...array_values($vars));
        } elseif (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $controller = $container->get($class);
            $result = $controller->{$method}($request, ...array_values($vars));
        } else {
            throw new \RuntimeException('Invalid route action defined.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return ApiResponse::success($result);
        }

        return new Response((string)$result);
    }
}

