<?php
// These functions are used by external scripts that hook into Misuzu.
// They will remain in a backwards compatible manner for the time being.

function user_session_create(int $userId, string $ipAddress, string $userAgent): string {
    try {
        $userInfo = \Misuzu\Users\User::byId($userId);
    } catch(\Misuzu\Users\UserNotFoundException $ex) {
        return '';
    }

    try {
        $sessionInfo = \Misuzu\Users\UserSession::create($userInfo, $ipAddress, $userAgent);
    } catch(\Misuzu\Users\UserSessionCreationFailedException $ex) {
        return '';
    }

    return $sessionInfo->getToken();
}

function user_session_bump_active(int $sessionId, ?string $ipAddress = null): void {
    try {
        $sessionInfo = \Misuzu\Users\UserSession::byId($sessionId);
    } catch(\Misuzu\Users\UserSessionNotFoundException $ex) {
        return;
    }

    if($ipAddress !== null)
        $sessionInfo->setLastRemoteAddress($ipAddress);

    $sessionInfo->bump();
}

function user_session_start(int $userId, string $sessionKey): bool {
    $session = \Misuzu\Users\UserSession::getCurrent();

    if($session !== null
        && $session->getToken() === $sessionKey
        && $session->getUserId() === $userId)
        return true;

    try {
        $session = \Misuzu\Users\UserSession::byToken($sessionKey);
    } catch(\Misuzu\Users\UserSessionNotFoundException $ex) {
        return false;
    }

    if($session->getUserId() !== $userId)
        return false;

    if($session->hasExpired()) {
        $session->delete();
        return false;
    }

    $session->setCurrent();
    return true;
}

function user_session_current(?string $variable = null, $default = null) {
    $getVar = !empty($variable);
    $session = \Misuzu\Users\UserSession::getCurrent();

    if($session === null)
        return $getVar ? $default : [];

    $data = [
        'session_id'           => $session->getId(),
        'user_id'              => $session->getUserId(),
        'session_ip'           => $session->getInitialRemoteAddress(),
        'session_ip_last'      => $session->getLastRemoteAddress(),
        'session_country'      => $session->getCountry(),
        'session_user_agent'   => $session->getUserAgent(),
        'session_key'          => $session->getToken(),
        'session_created'      => $session->getCreatedTime(),
        'session_expires'      => $session->getExpiresTime(),
        'session_active'       => ($date = $session->getActiveTime()) < 0 ? null : $date,
        'session_expires_bump' => $session->shouldBumpExpire() ? 1 : 0,
    ];

    if(!$getVar)
        return $data;

    return $data[$variable] ?? $default;
}

function user_session_active(): bool {
    return \Misuzu\Users\UserSession::hasCurrent()
        && !\Misuzu\Users\UserSession::getCurrent()->hasExpired();
}
