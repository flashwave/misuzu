<?php
namespace Misuzu\Http\Routing;

use InvalidArgumentException;
use Serializable;
use Misuzu\Http\HttpRequestMessage;
use Misuzu\Http\HttpResponseMessage;

class Route implements Serializable {
    private $methods = [];
    private $path = '';
    private $children = [];
    private $filters = [];
    private $parentRoute = null;

    private $handlerClass = null;
    private $handlerMethod = null;

    public function __construct(array $methods, string $path, ?string $method = null, ?string $class = null) {
        $this->methods = array_map('strtoupper', $methods);
        $this->path = $path;
        $this->handlerClass = $class;
        $this->handlerMethod = $method;
    }

    public static function create(array $methods, string $path, ?string $method = null, ?string $class = null): self {
        return new static($methods, $path, $method, $class);
    }
    public static function get(string $path, ?string $method = null, ?string $class = null): self {
        return self::create(['GET'], $path, $method, $class);
    }
    public static function post(string $path, ?string $method = null, ?string $class = null): self {
        return self::create(['POST'], $path, $method, $class);
    }
    public static function delete(string $path, ?string $method = null, ?string $class = null): self {
        return self::create(['DELETE'], $path, $method, $class);
    }
    public static function group(string $path, ?string $class = null): self {
        return self::create([''], $path, null, $class);
    }

    public function getHandlerClass(): string {
        return $this->handlerClass ?? ($this->parentRoute === null ? '' : $this->parentRoute->getHandlerClass());
    }
    public function setHandlerClass(string $class): self {
        $this->handlerClass = $class;
        return $this;
    }

    public function getHandlerMethod() {
        return $this->handlerMethod;
    }

    public function getParent(): ?self {
        return $this->parentRoute;
    }
    public function setParent(self $route): self {
        $this->parentRoute = $route;
        return $this;
    }

    public function getPath(): string {
        $path = $this->path;
        if($this->parentRoute !== null)
            $path = $this->parentRoute->getPath() . ($path[0] !== '.' ? '/' : '') . trim($path, '/');
        return $path;
    }
    public function setPath(string $path): self {
        $this->path = $path;
        return $this;
    }

    public function addFilters(string ...$filters): self {
        $this->filters = array_merge($this->filters, $filters);
        return $this;
    }
    public function getFilters(): array {
        $filters = $this->filters;
        if($this->parentRoute !== null)
            $filters += $this->parentRoute->getFilters();
        return $filters;
    }

    public function getChildren(): array {
        return $this->children;
    }
    public function addChildren(Route ...$routes): self {
        foreach($routes as $route)
            $this->children[] = $route->setParent($this);
        return $this;
    }

    public function match(HttpRequestMessage $request, array &$matches): bool {
        $matches = [];
        if(!in_array($request->getMethod(), $this->methods))
            return false;
        return preg_match('#^' . $this->getPath() . '$#', '/' . trim($request->getUri()->getPath(), '/'), $matches) === 1;
    }

    public function serialize() {
        return serialize([
            $this->methods,
            $this->getPath(),
            $this->getFilters(),
            $this->getHandlerClass(),
            $this->getHandlerMethod(),
        ]);
    }

    public function unserialize($data) {
        [$this->methods, $this->path, $this->filters, $this->handlerClass, $this->handlerMethod] = unserialize($data);
    }
}
