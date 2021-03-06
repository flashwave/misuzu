<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../misuzu.php';

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

$currentUser = User::getCurrent();

if($currentUser === null) {
    echo render_info_or_json($isXHR, 'You must be logged in to vote on polls.', 401);
    return;
}

$currentUserId = $currentUser->getId();

if($currentUser->isBanned()) {
    echo render_info_or_json($isXHR, 'You have been banned, check your profile for more information.', 403);
    return;
}
if($currentUser->isSilenced()) {
    echo render_info_or_json($isXHR, 'You have been silenced, check your profile for more information.', 403);
    return;
}

header(CSRF::header());

if(empty($_POST['poll']['id']) || !ctype_digit($_POST['poll']['id'])) {
    echo render_info_or_json($isXHR, "Invalid request.", 400);
    return;
}

$poll = forum_poll_get($_POST['poll']['id']);

if(empty($poll)) {
    echo "Poll {$poll['poll_id']} doesn't exist.<br>";
    return;
}

$topicInfo = forum_poll_get_topic($poll['poll_id']);

if(!is_null($topicInfo['topic_locked'])) {
    echo "The topic associated with this poll has been locked.<br>";
    return;
}

if(!forum_perms_check_user(
    MSZ_FORUM_PERMS_GENERAL, $topicInfo['forum_id'],
    $currentUserId, MSZ_FORUM_PERM_SET_READ
)) {
    echo "You aren't allowed to vote on this poll.<br>";
    return;
}

if($poll['poll_expired']) {
    echo "Voting for poll {$poll['poll_id']} has closed.<br>";
    return;
}

if(!$poll['poll_change_vote'] && forum_poll_has_voted($currentUserId, $poll['poll_id'])) {
    echo "Can't change vote for {$poll['poll_id']}<br>";
    return;
}

$answers = !empty($_POST['poll']['answers'])
    && is_array($_POST['poll']['answers'])
    ? $_POST['poll']['answers']
    : [];

if(count($answers) > $poll['poll_max_votes']) {
    echo "Too many votes for poll {$poll['poll_id']}<br>";
    return;
}

forum_poll_vote_remove($currentUserId, $poll['poll_id']);

foreach($answers as $answerId) {
    if(!is_string($answerId) || !ctype_digit($answerId)
        || !forum_poll_validate_option($poll['poll_id'], (int)$answerId)) {
        echo "Vote {$answerId} was invalid for {$poll['poll_id']}<br>";
        continue;
    }

    forum_poll_vote_cast($currentUserId, $poll['poll_id'], (int)$answerId);
}

url_redirect('forum-topic', ['topic' => $topicInfo['topic_id']]);
