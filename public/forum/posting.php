<?php
namespace Misuzu;

use Misuzu\Forum\ForumCategory;
use Misuzu\Forum\ForumCategoryNotFoundException;
use Misuzu\Forum\ForumTopic;
use Misuzu\Forum\ForumTopicNotFoundException;
use Misuzu\Net\IPAddress;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;

require_once '../../misuzu.php';

$currentUser = User::getCurrent();

if($currentUser === null) {
    echo render_error(401);
    return;
}

$currentUserId = $currentUser->getId();

if($currentUser->hasActiveWarning()) {
    echo render_error(403);
    return;
}

$forumPostingModes = [
    'create', 'edit', 'quote', 'preview',
];

if(!empty($_POST)) {
    $mode = !empty($_POST['post']['mode']) && is_string($_POST['post']['mode']) ? $_POST['post']['mode'] : 'create';
    $postId = !empty($_POST['post']['id']) && is_string($_POST['post']['id']) ? (int)$_POST['post']['id'] : 0;
    $topicId = !empty($_POST['post']['topic']) && is_string($_POST['post']['topic']) ? (int)$_POST['post']['topic'] : 0;
    $forumId = !empty($_POST['post']['forum']) && is_string($_POST['post']['forum']) ? (int)$_POST['post']['forum'] : 0;
} else {
    $mode = !empty($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : 'create';
    $postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;
    $topicId = !empty($_GET['t']) && is_string($_GET['t']) ? (int)$_GET['t'] : 0;
    $forumId = !empty($_GET['f']) && is_string($_GET['f']) ? (int)$_GET['f'] : 0;
}

if(!in_array($mode, $forumPostingModes, true)) {
    echo render_error(400);
    return;
}

if($mode === 'preview') {
    header('Content-Type: text/plain; charset=utf-8');

    $postText = (string)($_POST['post']['text']);
    $postParser = (int)($_POST['post']['parser']);

    if(!Parser::isValid($postParser)) {
        http_response_code(400);
        return;
    }

    http_response_code(200);
    echo Parser::instance($postParser)->parseText(htmlspecialchars($postText));
    return;
}

if(empty($postId) && empty($topicId) && empty($forumId)) {
    echo render_error(404);
    return;
}

if(!empty($postId)) {
    $post = forum_post_get($postId);

    if(isset($post['topic_id'])) { // should automatic cross-quoting be a thing? if so, check if $topicId is < 1 first
        $topicId = (int)$post['topic_id'];
    }
}

if(!empty($topicId))
    try {
        $topicInfo = ForumTopic::byId($topicId);
        $forumId = $topicInfo->getCategoryId();
    } catch(ForumTopicNotFoundException $ex) {}


try {
    $forumInfo = ForumCategory::byId($forumId);
} catch(ForumCategoryNotFoundException $ex) {
    echo render_error(404);
    return;
}

$perms = forum_perms_get_user($forumInfo->getId(), $currentUserId)[MSZ_FORUM_PERMS_GENERAL];

if($forumInfo->isArchived()
    || (!empty($topicInfo) && $topicInfo->isLocked() && !perms_check($perms, MSZ_FORUM_PERM_LOCK_TOPIC))
    || !perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM | MSZ_FORUM_PERM_CREATE_POST)
    || (empty($topicInfo) && !perms_check($perms, MSZ_FORUM_PERM_CREATE_TOPIC))) {
    echo render_error(403);
    return;
}

if(!$forumInfo->canHaveTopics()) {
    echo render_error(400);
    return;
}

$topicTypes = [];

if($mode === 'create' || $mode === 'edit') {
    $topicTypes[MSZ_TOPIC_TYPE_DISCUSSION] = 'Normal discussion';

    if(perms_check($perms, MSZ_FORUM_PERM_STICKY_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_STICKY] = 'Sticky topic';
    }
    if(perms_check($perms, MSZ_FORUM_PERM_ANNOUNCE_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_ANNOUNCEMENT] = 'Announcement';
    }
    if(perms_check($perms, MSZ_FORUM_PERM_GLOBAL_ANNOUNCE_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT] = 'Global Announcement';
    }
}

// edit mode stuff
if($mode === 'edit') {
    if(empty($post)) {
        echo render_error(404);
        return;
    }

    if(!perms_check($perms, $post['poster_id'] === $currentUserId ? MSZ_FORUM_PERM_EDIT_POST : MSZ_FORUM_PERM_EDIT_ANY_POST)) {
        echo render_error(403);
        return;
    }
}

$notices = [];

if(!empty($_POST)) {
    $topicTitle = $_POST['post']['title'] ?? '';
    $postText = $_POST['post']['text'] ?? '';
    $postParser = (int)($_POST['post']['parser'] ?? Parser::BBCODE);
    $topicType = isset($_POST['post']['type']) ? (int)$_POST['post']['type'] : null;
    $postSignature = isset($_POST['post']['signature']);

    if(!CSRF::validateRequest()) {
        $notices[] = 'Could not verify request.';
    } else {
        $isEditingTopic = empty($topicInfo) || ($mode === 'edit' && $post['is_opening_post']);

        if($mode === 'create') {
            $timeoutCheck = max(1, forum_timeout($forumInfo->getId(), $currentUserId));

            if($timeoutCheck < 5) {
                $notices[] = sprintf("You're posting too quickly! Please wait %s seconds before posting again.", number_format($timeoutCheck));
                $notices[] = "It's possible that your post went through successfully and you pressed the submit button twice by accident.";
            }
        }

        if($isEditingTopic) {
            $originalTopicTitle = empty($topicInfo) ? null : $topicInfo->getTitle();
            $topicTitleChanged = $topicTitle !== $originalTopicTitle;
            $originalTopicType =  empty($topicInfo) ? ForumTopic::TYPE_DISCUSSION : $topicInfo->getType();
            $topicTypeChanged = $topicType !== null && $topicType !== $originalTopicType;

            switch(forum_validate_title($topicTitle)) {
                case 'too-short':
                    $notices[] = 'Topic title was too short.';
                    break;

                case 'too-long':
                    $notices[] = 'Topic title was too long.';
                    break;
            }

            if($mode === 'create' && $topicType === null) {
                $topicType = array_key_first($topicTypes);
            } elseif(!array_key_exists($topicType, $topicTypes) && $topicTypeChanged) {
                $notices[] = 'You are not allowed to set this topic type.';
            }
        }

        if(!Parser::isValid($postParser)) {
            $notices[] = 'Invalid parser selected.';
        }

        switch(forum_validate_post($postText)) {
            case 'too-short':
                $notices[] = 'Post content was too short.';
                break;

            case 'too-long':
                $notices[] = 'Post content was too long.';
                break;
        }

        if(empty($notices)) {
            switch($mode) {
                case 'create':
                    if(!empty($topicInfo)) {
                        $topicInfo->bumpTopic();
                    } else {
                        $topicId = forum_topic_create(
                            $forumInfo->getId(),
                            $currentUserId,
                            $topicTitle,
                            $topicType
                        );
                    }

                    $postId = forum_post_create(
                        $topicId,
                        $forumInfo->getId(),
                        $currentUserId,
                        IPAddress::remote(),
                        $postText,
                        $postParser,
                        $postSignature
                    );
                    forum_topic_mark_read($currentUserId, $topicId, $forumInfo->getId());
                    $forumInfo->increaseTopicPostCount(empty($topicInfo));
                    break;

                case 'edit':
                    if(!forum_post_update($postId, IPAddress::remote(), $postText, $postParser, $postSignature, $postText !== $post['post_text'])) {
                        $notices[] = 'Post edit failed.';
                    }

                    if($isEditingTopic && ($topicTitleChanged || $topicTypeChanged)) {
                        if(!forum_topic_update($topicId, $topicTitle, $topicType)) {
                            $notices[] = 'Topic update failed.';
                        }
                    }
                    break;
            }

            if(empty($notices)) {
                $redirect = url(empty($topicInfo) ? 'forum-topic' : 'forum-post', [
                    'topic' => $topicId ?? 0,
                    'post' => $postId ?? 0,
                    'post_fragment' => 'p' . ($postId ?? 0),
                ]);
                redirect($redirect);
                return;
            }
        }
    }
}

if(!empty($topicInfo)) {
    Template::set('posting_topic', $topicInfo);
}

if($mode === 'edit') { // $post is pretty much sure to be populated at this point
    Template::set('posting_post', $post);
}

$displayInfo = forum_posting_info($currentUserId);

Template::render('forum.posting', [
    'posting_breadcrumbs' => forum_get_breadcrumbs($forumInfo->getId()),
    'global_accent_colour' => $forumInfo->getColour(),
    'posting_forum' => $forumInfo,
    'posting_info' => $displayInfo,
    'posting_notices' => $notices,
    'posting_mode' => $mode,
    'posting_types' => $topicTypes,
    'posting_defaults' => [
        'title' => $topicTitle ?? null,
        'type' => $topicType ?? null,
        'text' => $postText ?? null,
        'parser' => $postParser ?? null,
        'signature' => $postSignature ?? null,
    ],
]);
