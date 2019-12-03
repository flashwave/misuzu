<?php
namespace Misuzu;

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

if(!csrf_verify_request()) {
    echo render_info_or_json($isXHR, "Couldn't verify this request, please refresh the page and try again.", 403);
    return;
}

csrf_http_header();

if(!user_session_active()) {
    echo render_info_or_json($isXHR, 'You must be logged in to manage relations.', 401);
    return;
}

$userId = (int)user_session_current('user_id');

if(user_warning_check_expiration($userId, MSZ_WARN_BAN) > 0) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}

$subjectId = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$relationType = isset($_GET['m']) && is_string($_GET['m']) ? (int)$_GET['m'] : -1;

if(!user_relation_is_valid_type($relationType)) {
    echo render_info_or_json($isXHR, 'Invalid relation type.', 400);
    return;
}

if($userId < 1 || $subjectId < 1) {
    echo render_info_or_json($isXHR, "That user doesn't exist.", 400);
    return;
}

if(!user_relation_set($userId, $subjectId, $relationType)) {
    echo render_info_or_json($isXHR, "Failed to save relation.", 500);
    return;
}


if(($relationType === MSZ_USER_RELATION_NONE || $relationType === MSZ_USER_RELATION_FOLLOW)
    && in_array($subjectId, Config::get('relations.replicate', Config::TYPE_ARR))) {
    user_relation_set($subjectId, $userId, $relationType);
}

if(!$isXHR) {
    redirect($redirect);
    return;
}

echo json_encode([
    'user_id' => $userId,
    'subject_id' => $subjectId,
    'relation_type' => $relationType,
]);
