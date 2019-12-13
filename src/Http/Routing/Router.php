<?php
namespace Misuzu\Http\Routing;

use Misuzu\Http\HttpResponseMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Router implements RequestHandlerInterface {
    private static $instance = null;

    private $routesExact = [];
    private $routesRegex = [];

    public static function __callStatic(string $name, array $args) {
        return is_null(self::$instance) ? null : self::$instance->{$name}(...$args);
    }

    public function __construct() {}

    public function setInstance(): self {
        return self::$instance = $this;
    }

    public function addRoutes(Route ...$routes): self {
        foreach($routes as $route) {
            if($route->isRegex()) {
                $this->routesRegex[] = $route;
            } else {
                $this->routesExact[] = $route;
            }

            $this->addRoutes(...$route->getChildren());
        }

        return $this;
    }

    private function matchExact(RequestInterface $request, array &$matches): ?Route {
        foreach($this->routesExact as $route)
            if($route->match($request, $matches))
                return $route;
        return null;
    }

    private function matchRegex(RequestInterface $request, array &$matches): ?Route {
        foreach($this->routesRegex as $route)
            if($route->match($request, $matches))
                return $route;
        return null;
    }

    public function match(RequestInterface $request, array &$matches): ?Route {
        return $this->matchExact($request, $matches) ?? $this->matchRegex($request, $matches);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $matches = [];
        $route = $this->match($request, $matches);

        if($route === null)
            return new HttpResponseMessage(404);

        foreach($route->getFilters() as $filter) {
            $response = (new $filter)->process($request, $this);

            if($response !== null)
                return $response;
        }

        $response = $route->dispatch($request, ...array_slice($matches, 1));

        return $response;
    }
}
