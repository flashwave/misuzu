<?php
require_once '../../misuzu.php';

$postId = !empty($_GET['p']) && is_string($_GET['p']) ? (int)$_GET['p'] : 0;
$post = news_post_get($postId);

if(!$post) {
    echo render_error(404);
    return;
}

if($post['comment_section_id'] === null) {
    $commentsInfo = comments_category_create("news-{$post['post_id']}");

    if($commentsInfo) {
        $post['comment_section_id'] = $commentsInfo['category_id'];
        news_post_comments_set(
            $post['post_id'],
            $post['comment_section_id'] = $commentsInfo['category_id']
        );
    }
} else {
    $commentsInfo = comments_category_info($post['comment_section_id']);
}

echo tpl_render('news.post', [
    'post' => $post,
    'comments_perms' => comments_get_perms(user_session_current('user_id', 0)),
    'comments_category' => $commentsInfo,
    'comments' => comments_category_get($commentsInfo['category_id'], user_session_current('user_id', 0)),
]);
