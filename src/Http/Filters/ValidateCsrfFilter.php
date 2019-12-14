<?php
namespace Hanyuu\Filters;

use Misuzu\CSRF;
use Misuzu\Http\HttpResponseMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ValidateCsrfFilter implements FilterInterface {
    public function process(ServerRequestInterface $request): ?ResponseInterface {
        if($request->getMethod() !== 'GET' && $request->getMethod() !== 'DELETE') {
            $token = $request->getBodyParam('_csrf');

            if(empty($token) || !CSRF::validate($token))
                return new HttpResponseMessage(400);
        }

        return null;
    }
}
