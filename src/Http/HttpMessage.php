<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

abstract class HttpMessage implements MessageInterface {
    protected $version = '';
    protected $headers = [];
    protected $body = null;

    public function __construct(array $headers = [], string $version = '1.1') {
        $this->setProtocolVersion($version)->setHeaders($headers);
    }

    public function getProtocolVersion() {
        return $this->version;
    }
    public function setProtocolVersion(string $version): self {
        $this->version = $version;
        return $this;
    }
    public function withProtocolVersion($version) {
        return (clone $this)->setProtocolVersion($version);
    }

    public function getBody() {
        return $this->body;
    }
    public function setBody(StreamInterface $body): self {
        $this->body = $body;
        return $this;
    }
    public function withBody(StreamInterface $stream) {
        return (clone $this)->setBody($stream);
    }

    public function getHeaders() {
        return $this->headers;
    }
    public function setHeaders(array $headers): self {
        foreach($headers as $name => $value)
            $this->setHeader($name, $value);
        return $this;
    }
    public function getHeaderName(string $name, bool $nullOnNone = false): ?string {
        $lowerName = strtolower($name);

        foreach($this->headers as $headerName => $_)
            if(strtolower($headerName) === $name)
                return $headerName;

        return $nullOnNone ? null : $name;
    }
    public function hasHeader($name) {
        return $this->getHeaderName($name, true) !== null;
    }
    public function getHeader($name) {
        return $this->headers[$this->getHeaderName($name)] ?? [];
    }
    public function getHeaderLine($name) {
        $header = $this->getHeader($name);
        return implode(',', $header);
    }
    public function withHeader($name, $value) {
        if(!is_string($name) || empty($name))
            throw new InvalidArgumentException('Header name must be a string.');

        return (clone $this)->setHeader($name, $value);
    }
    public function setHeader(string $name, $value): self {
        if(!($isString = is_string($value)) && !is_array($value))
            throw new InvalidArgumentException('Value must be of type string or array.');

        $this->removeHeader($name);
        $this->headers[$name] = $isString ? [$value] : $value;

        return $this;
    }
    public function withAddedHeader($name, $value) {
        if(!is_string($name) || empty($name))
            throw new InvalidArgumentException('Header name must be a string.');

        return (clone $this)->appendHeader($name, $value);
    }
    public function appendHeader(string $name, $value): self {
        if(!($isString = is_string($value)) && !is_array($value))
            throw new InvalidArgumentException('Value must be of type string or array.');

        $existingName = $this->getHeaderName($name, true);
        $value = $isString ? [$value] : $value;

        if($existingName === null) {
            $this->headers[$existingName] = $value;
        } else {
            $this->headers[$name] = array_merge($this->headers, $value);
        }

        return $this;
    }
    public function withoutHeader($name) {
        if(!is_string($name) || empty($name))
            throw new InvalidArgumentException('Header name must be a string.');

        return (clone $this)->removeHeader($name);
    }
    public function removeHeader(string $name): self {
        $name = $this->getHeaderName($name, true);

        if($name !== null)
            unset($this->headers[$name]);

        return $this;
    }
}
