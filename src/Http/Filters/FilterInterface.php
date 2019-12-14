<?php
namespace Misuzu\Http\Filters;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface FilterInterface {
    public function process(ServerRequestInterface $request): ?ResponseInterface;
}
