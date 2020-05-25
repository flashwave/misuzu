<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Users\User;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserSessionNotFoundException;

require_once '../../misuzu.php';

if(!User::hasCurrent()) {
    echo render_error(401);
    return;
}

$errors = [];
$currentUser = User::getCurrent();
$currentSession = UserSession::getCurrent();
$currentUserId = $currentUser->getId();
$sessionActive = $currentSession->getId();;

if(!empty($_POST['session']) && CSRF::validateRequest()) {
    $currentSessionKilled = false;

    if(is_array($_POST['session'])) {
        foreach($_POST['session'] as $sessionId) {
            $sessionId = intval($sessionId);

            try {
                $sessionInfo = UserSession::byId($sessionId);
            } catch(UserSessionNotFoundException $ex) {}

            if(empty($sessionInfo) || $sessionInfo->getUserId() !== $currentUser->getId()) {
                $errors[] = "Session #{$sessionId} does not exist.";
                continue;
            } elseif($sessionInfo->getId() === $sessionActive) {
                $currentSessionKilled = true;
            }

            $sessionInfo->delete();
            AuditLog::create(AuditLog::PERSONAL_SESSION_DESTROY, [$sessionInfo->getId()]);
        }
    } elseif($_POST['session'] === 'all') {
        $currentSessionKilled = true;
        UserSession::purgeUser($currentUser);
        AuditLog::create(AuditLog::PERSONAL_SESSION_DESTROY_ALL);
    }

    if($currentSessionKilled) {
        url_redirect('index');
        return;
    }
}

$pagination = new Pagination(UserSession::countAll($currentUser), 15);

Template::render('settings.sessions', [
    'errors' => $errors,
    'session_list' => UserSession::all($pagination, $currentUser),
    'session_current' => $currentSession,
    'session_pagination' => $pagination,
]);
