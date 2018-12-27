<?php
require_once '../../misuzu.php';

if (!user_session_active()) {
    echo render_error(403);
    return;
}

if (!empty($_POST)) {
    $topicId = max(0, (int)($_POST['post']['topic'] ?? 0));
    $forumId = max(0, (int)($_POST['post']['forum'] ?? 0));
} else {
    $postId = max(0, (int)($_GET['p'] ?? 0));
    $topicId = max(0, (int)($_GET['t'] ?? 0));
    $forumId = max(0, (int)($_GET['f'] ?? 0));
}

if (empty($postId) && empty($topicId) && empty($forumId)) {
    echo render_error(404);
    return;
}

if (!empty($postId)) {
    $getPost = db_prepare('
        SELECT `post_id`, `topic_id`
        FROM `msz_forum_posts`
        WHERE `post_id` = :post_id
    ');
    $getPost->bindValue('post_id', $postId);
    $post = $getPost->execute() ? $getPost->fetch(PDO::FETCH_ASSOC) : false;

    if (isset($post['topic_id'])) { // should automatic cross-quoting be a thing? if so, check if $topicId is < 1 first
        $topicId = (int)$post['topic_id'];
    }
}

if (!empty($topicId)) {
    $getTopic = db_prepare('
        SELECT `topic_id`, `forum_id`, `topic_title`, `topic_locked`
        FROM `msz_forum_topics`
        WHERE `topic_id` = :topic_id
    ');
    $getTopic->bindValue('topic_id', $topicId);
    $topic = $getTopic->execute() ? $getTopic->fetch(PDO::FETCH_ASSOC) : false;

    if (isset($topic['forum_id'])) {
        $forumId = (int)$topic['forum_id'];
    }
}

if (!empty($forumId)) {
    $getForum = db_prepare('
        SELECT `forum_id`, `forum_name`, `forum_type`, `forum_archived`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');
    $getForum->bindValue('forum_id', $forumId);
    $forum = $getForum->execute() ? $getForum->fetch(PDO::FETCH_ASSOC) : false;
}

if (empty($forum)) {
    echo render_error(404);
    return;
}

$perms = forum_perms_get_user(MSZ_FORUM_PERMS_GENERAL, $forum['forum_id'], user_session_current('user_id', 0));

if ($forum['forum_archived']
    || !empty($topic['topic_locked'])
    || !perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM | MSZ_FORUM_PERM_CREATE_POST)
    || (empty($topic) && !perms_check($perms, MSZ_FORUM_PERM_CREATE_TOPIC))) {
    echo render_error(403);
    return;
}

if (!forum_may_have_topics($forum['forum_type'])) {
    echo render_error(400);
    return;
}

$notices = [];

if (!empty($_POST)) {
    if (!csrf_verify('forum_post', $_POST['csrf'] ?? '')) {
        $notices[] = 'Could not verify request.';
    } else {
        $topicTitle = $_POST['post']['title'] ?? '';
        $topicTitleValidate = forum_validate_title($topicTitle);
        $postText = $_POST['post']['text'] ?? '';
        $postTextValidate = forum_validate_post($postText);
        $postParser = (int)($_POST['post']['parser'] ?? MSZ_PARSER_BBCODE);

        if (!parser_is_valid($postParser)) {
            $notices[] = 'Invalid parser selected.';
        }

        switch ($postTextValidate) {
            case 'too-short':
                $notices[] = 'Post content was too short.';
                break;

            case 'too-long':
                $notices[] = 'Post content was too long.';
                break;
        }

        if (empty($topic)) {
            switch ($topicTitleValidate) {
                case 'too-short':
                    $notices[] = 'Topic title was too short.';
                    break;

                case 'too-long':
                    $notices[] = 'Topic title was too long.';
                    break;
            }
        }

        if (empty($notices)) {
            if (!empty($topic)) {
                forum_topic_bump($topic['topic_id']);
            } else {
                $topicId = forum_topic_create($forum['forum_id'], user_session_current('user_id', 0), $topicTitle);
            }

            $postId = forum_post_create(
                $topicId,
                $forum['forum_id'],
                user_session_current('user_id', 0),
                ip_remote_address(),
                $postText,
                $postParser
            );
            forum_topic_mark_read(user_session_current('user_id', 0), $topicId, $forum['forum_id']);

            header("Location: /forum/topic.php?p={$postId}#p{$postId}");
            return;
        }
    }
}

if (!empty($topic)) {
    tpl_var('posting_topic', $topic);
}

// fetches additional data for simulating a forum post
$getDisplayInfo = db_prepare('
    SELECT u.`user_country`, u.`user_created`, (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `user_id` = u.`user_id`
    ) AS `user_forum_posts`
    FROM `msz_users` as u
    WHERE `user_id` = :user_id
');
$getDisplayInfo->bindValue('user_id', user_session_current('user_id'));
$displayInfo = $getDisplayInfo->execute() ? $getDisplayInfo->fetch(PDO::FETCH_ASSOC) : [];

echo tpl_render('forum.posting', [
    'posting_breadcrumbs' => forum_get_breadcrumbs($forumId),
    'global_accent_colour' => forum_get_colour($forumId),
    'posting_forum' => $forum,
    'posting_info' => $displayInfo,
    'posting_notices' => $notices,
]);
