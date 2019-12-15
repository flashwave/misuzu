<?php
namespace Misuzu\Http\Filters;

use Misuzu\Http\HttpResponseMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EnforceLogInFilter implements FilterInterface {
    public function process(ServerRequestInterface $request): ?ResponseInterface {
        if(!user_session_active())
            return new HttpResponseMessage(403);

        return null;
    }
}
