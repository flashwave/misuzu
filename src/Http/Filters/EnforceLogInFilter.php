<?php
namespace Misuzu\Http\Filters;

use Misuzu\Http\HttpResponseMessage;
use Misuzu\Http\HttpRequestMessage;

class EnforceLogInFilter implements FilterInterface {
    public function process(HttpRequestMessage $request): ?HttpResponseMessage {
        if(!user_session_active())
            return new HttpResponseMessage(403);

        return null;
    }
}
