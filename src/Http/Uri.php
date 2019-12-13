<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface {
    private $scheme = '';
    private $user = '';
    private $password = '';
    private $host = '';
    private $port = null;
    private $path = '';
    private $query = '';
    private $fragment = '';
    private $originalString = '';

    public function __construct(string $uriString = '') {
        $this->originalString = $uriString;

        if(!empty($uriString)) {
            $uri = parse_url($uriString);

            if($uri === false)
                throw new InvalidArgumentException('URI cannot be parsed.');

            $this->setScheme($uri['scheme'] ?? '');
            $this->setUserInfo($uri['user'] ?? '', $uri['pass'] ?? null);
            $this->setHost($uri['host'] ?? '');
            $this->setPort($uri['port'] ?? null);
            $this->setPath($uri['path'] ?? '');
            $this->setQuery($uri['query'] ?? '');
            $this->setFragment($uri['fragment'] ?? '');
        }
    }

    public function getOriginalString(): string {
        return $this->originalString;
    }

    public function getScheme() {
        return $this->scheme;
    }
    public function setScheme(string $scheme): self {
        $this->scheme = $scheme;
        return $this;
    }
    public function withScheme($scheme) {
        if(!is_string($scheme))
            throw new InvalidArgumentException('Scheme must be a string.');

        return (clone $this)->setScheme($scheme);
    }

    public function getAuthority() {
        $authority = '';

        if(!empty($userInfo = $this->getUserInfo()))
            $authority .= $userInfo . '@';

        $authority .= $this->getHost();

        if(($port = $this->getPort()) !== null)
            $authority .= ':' . $port;

        return $authority;
    }

    public function getUserInfo() {
        $userInfo = $this->user;

        if(!empty($this->password))
            $userInfo .= ':' . $this->password;

        return $userInfo;
    }
    public function setUserInfo(string $user, ?string $password = null): self {
        $this->user = $user;
        $this->password = $password;
        return $this;
    }
    public function withUserInfo($user, $password = null) {
        return (clone $this)->setUserInfo($user, $password);
    }

    public function getHost() {
        return $this->host;
    }
    public function setHost(string $host): self {
        $this->host = $host;
        return $this;
    }
    public function withHost($host) {
        if(!is_string($host))
            throw new InvalidArgumentException('Hostname must be a string.');

        return (clone $this)->setHost($host);
    }

    public function getPort() {
        return $this->port;
    }
    public function setPort(?int $port): self {
        if($port !== null && ($port < 1 || $port > 0xFFFF))
            throw new InvalidArgumentException('Invalid port.');

        $this->port = $port;
        return $this;
    }
    public function withPort($port) {
        return (clone $this)->setPort($port);
    }

    public function getPath() {
        return $this->path;
    }
    public function setPath(string $path): self {
        $this->path = $path;
        return $this;
    }
    public function withPath($path) {
        if(!is_string($path))
            throw new InvalidArgumentException('Path must be a string.');

        return (clone $this)->setPath($path);
    }

    public function getQuery() {
        return $this->query;
    }
    public function setQuery(string $query): self {
        $this->query = $query;
        return $this;
    }
    public function withQuery($query) {
        if(!is_string($query))
            throw new InvalidArgumentException('Query string must be a string.');

        return (clone $this)->setQuery($query);
    }

    public function getFragment() {
        return $this->fragment;
    }
    public function setFragment(string $fragment): self {
        $this->fragment = $fragment;
        return $this;
    }
    public function withFragment($fragment) {
        return (clone $this)->setFragment($fragment);
    }

    public function __toString() {
        $string = '';

        if(!empty($scheme = $this->getScheme()))
            $string .= $scheme . ':';

        $authority = $this->getAuthority();
        $hasAuthority = !empty($authority);

        if($hasAuthority)
            $string .= '//' . $authority;

        $path = $this->getPath();
        $hasPath = !empty($path);

        if($hasAuthority && (!$hasPath || $path[0] !== '/'))
            $string .= '/';
        elseif(!$hasAuthority && $path[1] === '/')
            $path = '/' . trim($path, '/');

        $string .= $path;

        if(!empty($query = $this->getQuery()))
            $string .= '?' . $query;

        if(!empty($fragment = $this->getFragment()))
            $string .= '#' . $fragment;

        return $string;
    }
}
