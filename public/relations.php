<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../misuzu.php';

// basing whether or not this is an xhr request on whether a referrer header is present
// this page is never directy accessed, under normal circumstances
$redirect = !empty($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_X_MISUZU_XHR']) ? $_SERVER['HTTP_REFERER'] : '';
$isXHR = !$redirect;

if($isXHR) {
    header('Content-Type: application/json; charset=utf-8');
} elseif(!is_local_url($redirect)) {
    echo render_info('Possible request forgery detected.', 403);
    return;
}

if(!CSRF::validateRequest()) {
    echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
    return;
}

header(CSRF::header());

$currentUser = User::getCurrent();

if($currentUser === null) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage relations.', 401);
    return;
}

if(user_warning_check_expiration($currentUser->getId(), MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}

$subjectId = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$relationType = isset($_GET['m']) && is_string($_GET['m']) ? (int)$_GET['m'] : -1;

if(!user_relation_is_valid_type($relationType)) {
    echo render_info_or_json($isXHR, 'Invalid relation type.', 400);
    return;
}

if($currentUser->getId() < 1 || $subjectId < 1) {
    echo render_info_or_json($isXHR, "That user doesn't exist.", 400);
    return;
}

if(!user_relation_set($currentUser->getId(), $subjectId, $relationType)) {
    echo render_info_or_json($isXHR, "Failed to save relation.", 500);
    return;
}


if(($relationType === MSZ_USER_RELATION_NONE || $relationType === MSZ_USER_RELATION_FOLLOW)
    && in_array($subjectId, Config::get('relations.replicate', Config::TYPE_ARR))) {
    user_relation_set($subjectId, $currentUser->getId(), $relationType);
}

if(!$isXHR) {
    redirect($redirect);
    return;
}

echo json_encode([
    'user_id' => $currentUser->getId(),
    'subject_id' => $subjectId,
    'relation_type' => $relationType,
]);
