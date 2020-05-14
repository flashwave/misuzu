<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;

final class AuthHandler extends Handler {
    public static function legacy(HttpResponse $response, HttpRequest $request): void {
        $mode = $request->getQueryParam('m', FILTER_SANITIZE_STRING);
        $destination = [
            'logout' => 'auth-logout',
            'reset' => 'auth-reset',
            'forgot' => 'auth-forgot',
            'register' => 'auth-register',
        ][$mode] ?? 'auth-login';
        $response->redirect(url($destination), true);
    }
}
