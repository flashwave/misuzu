<?php
namespace Misuzu\Http\Filters;

use Misuzu\CSRF;
use Misuzu\Http\HttpResponseMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ValidateCsrfFilter implements FilterInterface {
    public function process(ServerRequestInterface $request): ?ResponseInterface {
        if($request->getMethod() !== 'GET' && $request->getMethod() !== 'DELETE') {
            $token = $request->getHeaderLine('X-Misuzu-CSRF');

            if(empty($token)) {
                $body = $request->getParsedBody();
                $token = isset($body['_csrf']) && is_string($body['_csrf']) ? $body['_csrf'] : null;
            }

            if(empty($token) || !CSRF::validate($token))
                return new HttpResponseMessage(400);
        }

        return null;
    }
}
