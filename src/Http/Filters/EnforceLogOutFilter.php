<?php
namespace Misuzu\Http\Filters;

use Misuzu\Http\HttpResponseMessage;
use Misuzu\Http\HttpRequestMessage;
use Misuzu\Users\User;
use Misuzu\Users\UserSession;

class EnforceLogOutFilter implements FilterInterface {
    public function process(HttpRequestMessage $request): ?HttpResponseMessage {
        if(UserSession::hasCurrent() || User::hasCurrent())
            return new HttpResponseMessage(404);

        return null;
    }
}
