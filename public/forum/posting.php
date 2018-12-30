<?php
require_once '../../misuzu.php';

if (!user_session_active()) {
    echo render_error(401);
    return;
}

if (user_warning_check_restriction(user_session_current('user_id', 0))) {
    echo render_error(403);
    return;
}

$forumPostingModes = [
    'create',   'edit',     'quote',
    'delete',   'restore',  'nuke',
];

if (!empty($_POST)) {
    $mode = $_POST['post']['mode'] ?? 'create';
    $postId = max(0, (int)($_POST['post']['id'] ?? 0));
    $topicId = max(0, (int)($_POST['post']['topic'] ?? 0));
    $forumId = max(0, (int)($_POST['post']['forum'] ?? 0));
} else {
    $mode = $_GET['m'] ?? 'create';
    $postId = max(0, (int)($_GET['p'] ?? 0));
    $topicId = max(0, (int)($_GET['t'] ?? 0));
    $forumId = max(0, (int)($_GET['f'] ?? 0));
}

if (!in_array($mode, $forumPostingModes, true)) {
    echo render_error(400);
    return;
}

if (empty($postId) && empty($topicId) && empty($forumId)) {
    echo render_error(404);
    return;
}

if (!empty($postId)) {
    $post = forum_post_get($postId);

    if (isset($post['topic_id'])) { // should automatic cross-quoting be a thing? if so, check if $topicId is < 1 first
        $topicId = (int)$post['topic_id'];
    }
}

if (!empty($topicId)) {
    $topic = forum_topic_fetch($topicId);

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

$perms = forum_perms_get_user(MSZ_FORUM_PERMS_GENERAL, $forum['forum_id'], user_session_current('user_id'));

if ($forum['forum_archived']
    || (!empty($topic['topic_locked']) && !perms_check($perms, MSZ_FORUM_PERM_LOCK_TOPIC))
    || !perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM | MSZ_FORUM_PERM_CREATE_POST)
    || (empty($topic) && !perms_check($perms, MSZ_FORUM_PERM_CREATE_TOPIC))) {
    echo render_error(403);
    return;
}

if (!forum_may_have_topics($forum['forum_type'])) {
    echo render_error(400);
    return;
}

$topicTypes = [];

if ($mode === 'create' || $mode === 'edit') {
    $topicTypes[MSZ_TOPIC_TYPE_DISCUSSION] = 'Normal discussion';

    if (perms_check($perms, MSZ_FORUM_PERM_STICKY_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_STICKY] = 'Sticky topic';
    }
    if (perms_check($perms, MSZ_FORUM_PERM_ANNOUNCE_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_ANNOUNCEMENT] = 'Announcement';
    }
    if (perms_check($perms, MSZ_FORUM_PERM_GLOBAL_ANNOUNCE_TOPIC)) {
        $topicTypes[MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT] = 'Global Announcement';
    }
}

// edit mode stuff
if ($mode === 'edit') {
    if (empty($post)) {
        echo render_error(404);
        return;
    }

    if (!perms_check($perms, $post['poster_id'] === user_session_current('user_id') ? MSZ_FORUM_PERM_EDIT_POST : MSZ_FORUM_PERM_EDIT_ANY_POST)) {
        echo render_error(403);
        return;
    }
}

$notices = [];

if (!empty($_POST)) {
    if (!csrf_verify('forum_post', $_POST['csrf'] ?? '')) {
        $notices[] = 'Could not verify request.';
    } else {
        $isEditingTopic = empty($topic) || ($mode === 'edit' && $post['is_opening_post']);

        if ($isEditingTopic) {
            $topicTitle = $_POST['post']['title'] ?? '';
            $originalTopicTitle = $topic['topic_title'] ?? null;
            $topicTitleChanged = $topicTitle !== $originalTopicTitle;
            $topicType = (int)($_POST['post']['type'] ?? array_key_first($topicTypes));
            $originalTopicType = (int)($topic['topic_type'] ?? 0);
            $topicTypeChanged = $topicType !== $originalTopicType;

            switch (forum_validate_title($topicTitle)) {
                case 'too-short':
                    $notices[] = 'Topic title was too short.';
                    break;

                case 'too-long':
                    $notices[] = 'Topic title was too long.';
                    break;
            }

            if (!array_key_exists($topicType, $topicTypes) && $topicTypeChanged) {
                $notices[] = 'You are not allowed to set this topic type.';
            }
        }

        $postText = $_POST['post']['text'] ?? '';
        $postParser = (int)($_POST['post']['parser'] ?? MSZ_PARSER_BBCODE);

        if (!parser_is_valid($postParser)) {
            $notices[] = 'Invalid parser selected.';
        }

        switch (forum_validate_post($postText)) {
            case 'too-short':
                $notices[] = 'Post content was too short.';
                break;

            case 'too-long':
                $notices[] = 'Post content was too long.';
                break;
        }

        if (empty($notices)) {
            switch ($mode) {
                case 'create':
                    if (!empty($topic)) {
                        forum_topic_bump($topic['topic_id']);
                    } else {
                        $topicId = forum_topic_create(
                            $forum['forum_id'],
                            user_session_current('user_id', 0),
                            $topicTitle,
                            $topicType
                        );
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
                    break;

                case 'edit':
                    if (!forum_post_update($postId, ip_remote_address(), $postText, $postParser, $postText !== $post['post_text'])) {
                        $notices[] = 'Post edit failed.';
                    }

                    if ($isEditingTopic && ($topicTitleChanged || $topicTypeChanged)) {
                        if (!forum_topic_update($topicId, $topicTitle, $topicType)) {
                            $notices[] = 'Topic update failed.';
                        }
                    }
                    break;
            }

            if (empty($notices)) {
                header("Location: /forum/topic.php?p={$postId}#p{$postId}");
                return;
            }
        }
    }
}

if (!empty($topic)) {
    tpl_var('posting_topic', $topic);
}

if ($mode === 'edit') { // $post is pretty much sure to be populated at this point
    tpl_var('posting_post', $post);
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
    'posting_mode' => $mode,
    'posting_types' => $topicTypes,
]);
