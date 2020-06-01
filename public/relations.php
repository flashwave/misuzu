<?php
namespace Misuzu;

use Misuzu\Config;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserRelation;

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

if($currentUser->isBanned()) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}

$subjectId = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$relationType = isset($_GET['m']) && is_string($_GET['m']) ? (int)$_GET['m'] : -1;

if($relationType < 0) {
    echo render_info_or_json($isXHR, 'Invalid relation type.', 400);
    return;
}

$relationType = $relationType > 0 ? UserRelation::TYPE_FOLLOW : UserRelation::TYPE_NONE;

try {
    $subjectInfo = User::byId($subjectId);
} catch(UserNotFoundException $ex) {
    echo render_info_or_json($isXHR, "That user doesn't exist.", 400);
    return;
}

if($relationType > 0)
    $subjectInfo->addFollower($currentUser);
else
    $subjectInfo->removeRelation($currentUser);

if(in_array($subjectInfo->getId(), Config::get('relations.replicate', Config::TYPE_ARR))) {
    if($relationType > 0)
        $currentUser->addFollower($subjectInfo);
    else
        $currentUser->removeRelation($subjectInfo);
}

if(!$isXHR) {
    redirect($redirect);
    return;
}

echo json_encode([
    'user_id' => $currentUser->getId(),
    'subject_id' => $subjectInfo->getId(),
    'relation_type' => $relationType,
]);
