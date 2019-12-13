<?php
namespace Misuzu\Http\Handlers;

final class AuthHandler extends Handler {
    public static function legacy(Response $response, Request $request): void {
        $query = $request->getQueryParams();
        $mode = isset($query['m']) && is_string($query['m']) ? $query['m'] : '';
        $destination = [
            'logout' => 'auth-logout',
            'reset' => 'auth-reset',
            'forgot' => 'auth-forgot',
            'register' => 'auth-register',
        ][$mode] ?? 'auth-login';
        $response->redirect(url($destination), true);
    }
}
