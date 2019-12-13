<?php
namespace Misuzu\Http\Routing;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route {
    private $methods = [];
    private $path = '';
    private $regex = false;
    private $handler;
    private $children = [];
    private $filters = [];

    public function __construct(array $methods, string $path, bool $regex, $handler) {
        $this->methods = array_map('strtoupper', $methods);
        $this->regex = $regex;
        $this->handler = $handler;
        $this->path = $path;
    }

    public static function create(array $methods, string $path, $regex = null, $callable = null): self {
        return new static($methods, $path, is_bool($regex) ? $regex : false, is_bool($regex) ? $callable : $regex);
    }
    public static function get(string $path, $regex = null, $callable = null): self {
        return self::create(['GET'], $path, $regex, $callable);
    }
    public static function post(string $path, $regex = null, $callable = null): self {
        return self::create(['POST'], $path, $regex, $callable);
    }

    public function isRegex(): bool {
        return $this->regex;
    }
    public function setRegex(): self {
        $this->regex = true;
        foreach($this->children as $child)
            $child->setRegex();
        return $this;
    }

    public function getPath(): string {
        return $this->path;
    }
    public function setPath(string $path): self {
        $this->path = $path;
        return $this;
    }

    public function addFilters(string ...$filters): self {
        $this->filters = array_merge($this->filters, $filters);
        foreach($this->children as $child)
            $child->addFilters(...$filters);
        return $this;
    }
    public function getFilters(): array {
        return $this->filters;
    }

    public function getChildren(): array {
        return $this->children;
    }
    public function addChildren(Route ...$routes): self {
        foreach($routes as $route) {
            $route->setPrefix($this->getPath())->addFilters(...$this->getFilters());

            if($this->isRegex())
                $route->setRegex();

            $this->children[] = $route;
        }
        return $this;
    }

    public function setPrefix(string $prefix): self {
        foreach($this->children as $child)
            $child->setPrefix($prefix);
        return $this->setPath($prefix . '/' . trim($this->getPath(), '/'));
    }

    public function match(RequestInterface $request, array &$matches): bool {
        $matches = [$this->getPath()];

        if(!in_array($request->getMethod(), $this->methods))
            return false;

        $requestPath = $request->getUri()->getPath();

        return $this->isRegex()
            ? preg_match('#^' . $this->getPath() . '$#', $requestPath, $matches)
            : $this->getPath() === $requestPath;
    }

    public function dispatch(ServerRequestInterface $request, ...$args): ResponseInterface {
        $response = new RouterResponseMessage(200);
        $result = null;

        array_unshift($args, $response, $request);

        if(is_array($this->handler)) {
            if(method_exists($this->handler[0] ?? '', $this->handler[1] ?? '')) {
                $handlerClass = new $this->handler[0]($response, $request);
                $result = $handlerClass->{$this->handler[1]}(...$args);
            }
        } elseif(is_callable($this->handler)) {
            $result = call_user_func_array($this->handler, $args);
        }

        if($result !== null) {
            $resultType = gettype($result);

            switch($resultType) {
                case 'array':
                case 'object':
                    $response->setJson($result);
                    break;
                case 'integer':
                    $response->setStatusCode($result);
                    break;
                default:
                    $response->setHtml($result);
                    break;
            }
        }

        return $response;
    }
}
