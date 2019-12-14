<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class HttpServerRequestMessage extends HttpRequestMessage implements ServerRequestInterface {
    private $server = [];
    private $cookies = [];
    private $query = [];
    private $files = [];
    private $attributes = [];
    private $parsedBody = null;
    private $bodyStream = null;

    public function __construct(
        string $method,
        UriInterface $uri,
        array $serverParams = [],
        array $headers = [],
        array $queryParams = [],
        array $uploadedFiles = [],
        $parsedBody = null,
        StreamInterface $rawBody = null
    ) {
        parent::__construct($method, $uri, $headers);
        $this->setServerParams($serverParams);
        $this->setQueryParams($queryParams);
        $this->setUploadedFiles($uploadedFiles);
        $this->setParsedBody($parsedBody);
        $this->setBody($rawBody);
    }

    public function getServerParams() {
        return $this->server;
    }
    public function setServerParams(array $serverParams): self {
        $this->server = $serverParams;
        return $this;
    }

    public function getCookieParams() {
        return $this->cookies;
    }
    public function setCookieParams(array $cookies): self {
        $this->cookies = $cookies;
        return $this;
    }
    public function withCookieParams(array $cookies) {
        return (clone $this)->setCookieParams($cookies);
    }

    public function getQueryParams() {
        return $this->query;
    }
    public function setQueryParams(array $query): self {
        $this->query = $query;
        return $this;
    }
    public function withQueryParams(array $query) {
        return (clone $this)->setQueryParams($query);
    }

    public function getUploadedFiles() {
        return $this->files;
    }
    public function setUploadedFiles(array $uploadedFiles): self {
        $this->files = $uploadedFiles;
        return $this;
    }
    public function withUploadedFiles(array $uploadedFiles): self {
        return (clone $this)->setUploadedFiles($uploadedFiles);
    }

    public function getParsedBody() {
        return $this->parsedBody;
    }
    public function setParsedBody($data): self {
        if(!is_array($data) && !is_object($data) && $data !== null)
            throw new InvalidArgumentException('Parsed body must by of type array, object or null.');
        $this->parsedBody = $data;
        return $this;
    }
    public function withParsedBody($data) {
        return (clone $this)->setParsedBody($data);
    }

    public function getAttributes() {
        return $this->attributes;
    }
    public function getAttribute($name, $default = null) {
        return $this->attributes[$name] ?? $default;
    }
    public function setAttribute(string $name, $value): self {
        $this->attributes[$name] = $value;
        return $this;
    }
    public function withAttribute($name, $value) {
        return (clone $this)->setAttribute($name, $value);
    }
    public function removeAttribute(string $name): self {
        unset($this->attributes[$name]);
        return $this;
    }
    public function withoutAttribute($name) {
        return (clone $this)->removeAttribute($name);
    }

    private static function getRequestHeaders(): array {
        if(function_exists('getallheaders'))
            return getallheaders();

        $headers = [];

        foreach($_SERVER as $key => $value) {
            if(substr($key, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $reqHeaders[$key] = $value;
            } elseif($key === 'CONTENT_TYPE') {
                $reqHeaders['Content-Type'] = $value;
            } elseif($key === 'CONTENT_LENGTH') {
                $reqHeaders['Content-Length'] = $value;
            }
        }

        if(!isset($headers['Authorization'])) {
            if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif(isset($_SERVER['PHP_AUTH_USER'])) {
                $password = $_SERVER['PHP_AUTH_PW'] ?? '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $password);
            } elseif(isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    public static function fromGlobals(): HttpServerRequestMessage {
        return new static(
            $_SERVER['REQUEST_METHOD'],
            new Uri('/' . trim($_SERVER['REQUEST_URI'] ?? '', '/')),
            $_SERVER,
            self::getRequestHeaders(),
            $_GET,
            UploadedFile::createFromFILES($_FILES),
            $_POST,
            Stream::createFromFile('php://input')
        );
    }
}
