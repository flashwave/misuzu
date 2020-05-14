<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Misuzu\Stream;
use Misuzu\Uri;

class HttpRequestMessage extends HttpMessage {
    private $method = null;
    private $uri = null;
    private $requestTarget = null;
    private $server = [];
    private $cookies = [];
    private $query = [];
    private $files = [];
    private $parsedBody = null;
    private $bodyStream = null;

    public function __construct(
        string $method,
        Uri $uri,
        array $serverParams = [],
        array $headers = [],
        array $queryParams = [],
        array $uploadedFiles = [],
        $parsedBody = null,
        Stream $rawBody = null
    ) {
        parent::__construct($headers);
        $this->setMethod($method);
        $this->setUri($uri);
        $this->setServerParams($serverParams);
        $this->setQueryParams($queryParams);
        $this->setUploadedFiles($uploadedFiles);
        $this->setParsedBody($parsedBody);
        $this->setBody($rawBody);
    }

    public function getMethod() {
        return $this->method;
    }
    private function setMethod(string $method): self {
        if(empty($method))
            throw new InvalidArgumentException('Invalid method name.');
        $this->method = $method;
        return $this;
    }

    public function getUri() {
        return $this->uri;
    }
    private function setUri(Uri $uri, bool $preserveHost = false): self {
        $this->uri = $uri;

        if(!$preserveHost || !$this->hasHeader('Host'))
            $this->applyHostHeader();

        return $this;
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
    private function setRequestTarget(?string $requestTarget): self {
        if(preg_match('#\s#', $requestTarget))
            throw new InvalidArgumentException('Request target may not contain spaces.');

        $this->requestTarget = $requestTarget;
        return $this;
    }

    public function getServerParams() {
        return $this->server;
    }
    private function setServerParams(array $serverParams): self {
        $this->server = $serverParams;
        return $this;
    }
    public function getServerParam(string $name, int $filter = FILTER_DEFAULT, $options = null) {
        if(!isset($this->server[$name]))
            return null;
        return filter_var($this->server[$name], $filter, $options);
    }

    public function getRemoteAddress(): string {
        return $this->server['REMOTE_ADDR'] ?? '::1';
    }

    public function getCookieParams() {
        return $this->cookies;
    }
    private function setCookieParams(array $cookies): self {
        $this->cookies = $cookies;
        return $this;
    }
    public function getCookieParam(string $name, int $filter = FILTER_DEFAULT, $options = null) {
        if(!isset($this->cookies[$name]))
            return null;
        return filter_var($this->cookies[$name], $filter, $options);
    }

    public function getQueryParams() {
        return $this->query;
    }
    private function setQueryParams(array $query): self {
        $this->query = $query;
        return $this;
    }
    public function getQueryParam(string $name, int $filter = FILTER_DEFAULT, $options = null) {
        if(!isset($this->query[$name]))
            return null;
        return filter_var($this->query[$name], $filter, $options);
    }

    public function getUploadedFiles() {
        return $this->files;
    }
    private function setUploadedFiles(array $uploadedFiles): self {
        $this->files = $uploadedFiles;
        return $this;
    }

    public function getParsedBody() {
        return $this->parsedBody;
    }
    private function setParsedBody($data): self {
        if(!is_array($data) && !is_object($data) && $data !== null)
            throw new InvalidArgumentException('Parsed body must by of type array, object or null.');
        $this->parsedBody = $data;
        return $this;
    }
    public function getBodyParam(string $name, int $filter = FILTER_DEFAULT, $options = null) {
        if($this->parsedBody === null)
            return null;

        $value = null;

        if(is_object($this->parsedBody) && isset($this->parsedBody->{$name})) {
            $value = $this->parsedBody->{$name};
        } elseif(is_array($this->parsedBody) && isset($this->parsedBody[$name])) {
            $value = $this->parsedBody[$name];
        }

        return filter_var($value, $filter, $options);
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

    public static function fromGlobals(): HttpRequestMessage {
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
