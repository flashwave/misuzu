<?php
namespace Misuzu\Http\Routing;

use Misuzu\Http\HttpRequestMessage;
use Misuzu\Http\HttpResponseMessage;

class Router {
    private static $instance = null;

    private $handlerFormat = '';
    private $filterFormat = '';
    private $routes = [];

    public function __call(string $name, array $args) {
        if($name[0] === '_')
            return null;
        return $this->{'_' . $name}(...$args);
    }

    public static function __callStatic(string $name, array $args) {
        if($name[0] === '_')
            return null;
        if(self::$instance === null)
            (new static)->setInstance();
        return self::$instance->{'_' . $name}(...$args);
    }

    public function setInstance(): self {
        return self::$instance = $this;
    }

    public function _getHandlerFormat(): string {
        return $this->handlerFormat;
    }
    public function _setHandlerFormat(string $format): self {
        $this->handlerFormat = $format;
        return $this;
    }

    public function _getFilterFormat(): string {
        return $this->filterFormat;
    }
    public function _setFilterFormat(string $format): self {
        $this->filterFormat = $format;
        return $this;
    }

    public function _addRoutes(Route ...$routes): self {
        foreach($routes as $route) {
            $this->routes[] = $route;
            $this->addRoutes(...$route->getChildren());
        }
        return $this;
    }

    // Unused, might be useful for the future to immediately smash the routes list together
    //  without having to propagate children.
    // Should obviously not be in debug mode and should also be nuked after a pull on stable.
    public function _getData(): string {
        return serialize([$this->routes]);
    }
    public function _setData(string $data): void {
        [$this->routes] = unserialize($data);
    }

    private function match(HttpRequestMessage $request, array &$matches): ?Route {
        foreach($this->routes as $route)
            if($route->match($request, $matches))
                return $route;
        return null;
    }

    public function _handle(HttpRequestMessage $request): HttpResponseMessage {
        $matches = [];
        $route = $this->match($request, $matches);
        array_shift($matches);

        if($route === null)
            return new HttpResponseMessage(404);

        foreach($route->getFilters() as $filter) {
            if(!class_exists($filter))
                $filter = sprintf($this->_getFilterFormat(), $filter);
            $response = (new $filter)->process($request, $this);
            if($response !== null)
                return $response;
        }

        $response = new HttpResponseMessage(200);
        $result = null;
        array_unshift($matches, $response, $request);

        $handlerMethod = $route->getHandlerMethod();
        if(is_callable($handlerMethod)) {
            $result = call_user_func_array($handlerMethod, $args);
        } elseif($handlerMethod[0] === '/') {
            $response->redirect($handlerMethod);
        } else {
            $handlerClass = $route->getHandlerClass();
            if(!empty($handlerClass)) {
                if(!class_exists($handlerClass))
                    $handlerClass = sprintf($this->_getHandlerFormat(), $handlerClass);
                $handlerClass = new $handlerClass($response, $request);
                $result = $handlerClass->{$handlerMethod}(...$matches);
            }
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
