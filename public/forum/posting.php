<?php
use Misuzu\Database;
use Misuzu\Net\IPAddress;

require_once __DIR__ . '/../../misuzu.php';

if (!$app->hasActiveSession()) {
    header('Location: /');
    return;
}

$postRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

$db = Database::connection();
$templating = $app->getTemplating();

// ORDER OF CHECKING
//  - $postId non-zero: enter quote mode
//  - $topicId non-zero: enter reply mode
//  - $forumId non-zero: enter create mode
//  - all zero: enter explode mode
if ($postRequest) {
    $topicId = max(0, (int)($_POST['post']['topic'] ?? 0));
    $forumId = max(0, (int)($_POST['post']['forum'] ?? 0));
} else {
    $postId = max(0, (int)($_GET['p'] ?? 0));
    $topicId = max(0, (int)($_GET['t'] ?? 0));
    $forumId = max(0, (int)($_GET['f'] ?? 0));
}

if (!empty($postId)) {
    $getPost = $db->prepare('
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
    $getTopic = $db->prepare('
        SELECT `topic_id`, `forum_id`, `topic_title`
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
    $getForum = $db->prepare('
        SELECT `forum_id`, `forum_name`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');
    $getForum->bindValue('forum_id', $forumId);
    $forum = $getForum->execute() ? $getForum->fetch() : false;
}

if ($postRequest) {
    $createPost = $db->prepare('
        INSERT INTO `msz_forum_posts`
            (`topic_id`, `forum_id`, `user_id`, `post_ip`, `post_text`)
        VALUES
            (:topic_id, :forum_id, :user_id, INET6_ATON(:post_ip), :post_text)
    ');

    if (isset($topic)) {
        $bumpTopic = $db->prepare('
            UPDATE `msz_forum_topics`
            SET `topic_bumped` = NOW()
            WHERE `topic_id` = :topic_id
        ');
        $bumpTopic->bindValue('topic_id', $topic['topic_id']);
        $bumpTopic->execute();
    } else {
        $createTopic = $db->prepare('
            INSERT INTO `msz_forum_topics`
                (`forum_id`, `user_id`, `topic_title`)
            VALUES
                (:forum_id, :user_id, :topic_title)
        ');
        $createTopic->bindValue('forum_id', $forum['forum_id']);
        $createTopic->bindValue('user_id', $app->getUserId());
        $createTopic->bindValue('topic_title', $_POST['post']['title']);
        $createTopic->execute();
        $topicId = (int)$db->lastInsertId();
    }

    $createPost->bindValue('topic_id', $topicId);
    $createPost->bindValue('forum_id', $forum['forum_id']);
    $createPost->bindValue('user_id', $app->getUserId());
    $createPost->bindValue('post_ip', IPAddress::remote()->getString());
    $createPost->bindValue('post_text', $_POST['post']['text']);
    $createPost->execute();
    $postId = $db->lastInsertId();

    header("Location: /forum/topic.php?p={$postId}#p{$postId}");
    return;
}

$lastParent = $forumId;
$breadcrumbs = [];
$getBreadcrumb = $db->prepare('
    SELECT `forum_id`, `forum_name`, `forum_parent`
    FROM `msz_forum_categories`
    WHERE `forum_id` = :forum_id
');

while ($lastParent > 0) {
    $getBreadcrumb->bindValue('forum_id', $lastParent);
    $breadcrumb = $getBreadcrumb->execute() ? $getBreadcrumb->fetch() : [];

    if (!$breadcrumb) {
        break;
    }

    $breadcrumbs[$breadcrumb['forum_name']] = '/forum/forum.php?f=' . $breadcrumb['forum_id'];
    $lastParent = $breadcrumb['forum_parent'];
}

$breadcrumbs['Forums'] = '/forum/';
$breadcrumbs = array_reverse($breadcrumbs);

if (!empty($topic)) {
    $templating->var('posting_topic', $topic);
}

echo $templating->render('forum.posting', [
    'posting_breadcrumbs' => $breadcrumbs,
    'posting_forum' => $forum,
]);
