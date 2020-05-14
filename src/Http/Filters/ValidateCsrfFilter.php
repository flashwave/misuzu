<?php
namespace Misuzu\Http\Filters;

use Misuzu\CSRF;
use Misuzu\Http\HttpResponseMessage;
use Misuzu\Http\HttpRequestMessage;

class ValidateCsrfFilter implements FilterInterface {
    public function process(HttpRequestMessage $request): ?HttpResponseMessage {
        if($request->getMethod() !== 'GET' && $request->getMethod() !== 'DELETE') {
            $token = $request->getHeaderLine('X-Misuzu-CSRF');

            if(empty($token))
                $token = $request->getBodyParam('_csrf', FILTER_SANITIZE_STRING);

            if(empty($token) || !CSRF::validate($token))
                return new HttpResponseMessage(400);
        }

        return null;
    }
}
