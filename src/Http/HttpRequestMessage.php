<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class HttpRequestMessage extends HttpMessage implements RequestInterface {
    private $method = null;
    private $uri = null;
    private $requestTarget = null;

    public function __construct(string $method, UriInterface $uri, array $headers = []) {
        parent::__construct($headers);
        $this->setMethod($method);
        $this->setUri($uri);
    }

    public function getMethod() {
        return $this->method;
    }
    public function setMethod(string $method): self {
        if(empty($method))
            throw new InvalidArgumentException('Invalid method name.');
        $this->method = $method;
        return $this;
    }
    public function withMethod($method) {
        return (clone $this)->setMethod($method);
    }

    public function getUri() {
        return $this->uri;
    }
    public function setUri(UriInterface $uri, bool $preserveHost = false): self {
        $this->uri = $uri;

        if(!$preserveHost || !$this->hasHeader('Host'))
            $this->applyHostHeader();

        return $this;
    }
    public function withUri($uri, $preserveHost = false) {
        return (clone $this)->setUri($uri, $preserveHost);
    }

    private function applyHostHeader(): void {
        $uri = $this->getUri();

        if(empty($host = $uri->getHost()))
            return;

        if(($port = $uri->getPort()) !== null)
            $host .= ":{$port}";

        $headerName = $this->getHeaderName('Host');
        $this->headers = [$headerName => [$host]] + $this->headers;
    }

    public function getRequestTarget() {
        if($this->requestTarget !== null)
            return $this->requestTarget;

        $uri = $this->getUri();
        $target = $uri->getPath();
        $query = $uri->getQuery();

        if(empty($target))
            $target = '/';

        if(!empty($query))
            $target .= '?' . $query;

        return $target;
    }
    public function setRequestTarget(?string $requestTarget): self {
        if(preg_match('#\s#', $requestTarget))
            throw new InvalidArgumentException('Request target may not contain spaces.');

        $this->requestTarget = $requestTarget;
        return $this;
    }
    public function withRequestTarget($requestTarget) {
        return (clone $this)->setRequestTarget($requestTarget);
    }
}
