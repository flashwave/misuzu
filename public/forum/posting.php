<?php
use Misuzu\Database;
use Misuzu\Net\IPAddress;

require_once __DIR__ . '/../../misuzu.php';

$templating = $app->getTemplating();

if (!$app->hasActiveSession()) {
    echo render_error(403);
    return;
}

$postRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($postRequest) {
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
    $getPost = Database::prepare('
        SELECT `post_id`, `topic_id`
        FROM `msz_forum_posts`
        WHERE `post_id` = :post_id
    ');
    $getPost->bindValue('post_id', $postId);
    $post = $getPost->execute() ? $getPost->fetch() : false;

    if (isset($post['topic_id'])) { // should automatic cross-quoting be a thing? if so, check if $topicId is < 1 first
        $topicId = (int)$post['topic_id'];
    }
}

if (!empty($topicId)) {
    $getTopic = Database::prepare('
        SELECT `topic_id`, `forum_id`, `topic_title`, `topic_locked`
        FROM `msz_forum_topics`
        WHERE `topic_id` = :topic_id
    ');
    $getTopic->bindValue('topic_id', $topicId);
    $topic = $getTopic->execute() ? $getTopic->fetch() : false;

    if (isset($topic['forum_id'])) {
        $forumId = (int)$topic['forum_id'];
    }
}

if (!empty($forumId)) {
    $getForum = Database::prepare('
        SELECT `forum_id`, `forum_name`, `forum_type`, `forum_archived`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');
    $getForum->bindValue('forum_id', $forumId);
    $forum = $getForum->execute() ? $getForum->fetch() : false;
}

if (empty($forum)) {
    echo render_error(404);
    return;
}

if ($forum['forum_type'] != MSZ_FORUM_TYPE_DISCUSSION) {
    echo render_error(400);
    return;
}

if ($forum['forum_archived'] || !empty($topic['topic_locked'])) {
    echo render_error(403);
    return;
}

if ($postRequest) {
    if (!tmp_csrf_verify($_POST['csrf'] ?? '')) {
        echo 'Could not verify request.';
        return;
    }

    $topicTitle = $_POST['post']['title'] ?? '';
    $topicTitleValidate = forum_validate_title($topicTitle);
    $postText = $_POST['post']['text'] ?? '';
    $postTextValidate = forum_validate_post($postText);

    switch ($postTextValidate) {
        case 'too-short':
            echo 'Post content was too short.';
            return;

        case 'too-long':
            echo 'Post content was too long.';
            return;
    }

    if (isset($topic)) {
        forum_topic_bump($topic['topic_id']);
    } else {
        switch ($topicTitleValidate) {
            case 'too-short':
                echo 'Topic title was too short.';
                return;

            case 'too-long':
                echo 'Topic title was too long.';
                return;
        }

        $topicId = forum_topic_create($forum['forum_id'], $app->getUserId(), $topicTitle);
    }

    $postId = forum_post_create(
        $topicId,
        $forum['forum_id'],
        $app->getUserId(),
        IPAddress::remote()->getString(),
        $postText,
        MSZ_FORUM_POST_PARSER_BBCODE
    );
    forum_topic_mark_read($app->getUserId(), $topicId, $forum['forum_id']);

    header("Location: /forum/topic.php?p={$postId}#p{$postId}");
    return;
}

if (!empty($topic)) {
    $templating->var('posting_topic', $topic);
}

echo $templating->render('forum.posting', [
    'posting_breadcrumbs' => forum_get_breadcrumbs($forumId),
    'posting_forum' => $forum,
]);
