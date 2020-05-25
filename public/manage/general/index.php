<?php
namespace Misuzu;

require_once '../../../misuzu.php';

$statistics = DB::query('
    SELECT
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
    ) AS `stat_users_total`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
        WHERE `user_deleted` IS NOT NULL
    ) AS `stat_users_deleted`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users`
        WHERE `user_active` IS NOT NULL
        AND `user_deleted` IS NULL
    ) AS `stat_users_active`,
    (
        SELECT COUNT(*)
        FROM `msz_audit_log`
    ) AS `stat_audit_logs`,
    (
        SELECT COUNT(`change_id`)
        FROM `msz_changelog_changes`
    ) AS `stat_changelog_entries`,
    (
        SELECT COUNT(`category_id`)
        FROM `msz_comments_categories`
    ) AS `stat_comment_categories_total`,
    (
        SELECT COUNT(`category_id`)
        FROM `msz_comments_categories`
        WHERE `category_locked` IS NOT NULL
    ) AS `stat_comment_categories_locked`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
    ) AS `stat_comment_posts_total`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_deleted` IS NOT NULL
    ) AS `stat_comment_posts_deleted`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_reply_to` IS NOT NULL
    ) AS `stat_comment_posts_replies`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_pinned` IS NOT NULL
    ) AS `stat_comment_posts_pinned`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `comment_edited` IS NOT NULL
    ) AS `stat_comment_posts_edited`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_comments_votes`
        WHERE `comment_vote` > 0
    ) AS `stat_comment_likes`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_comments_votes`
        WHERE `comment_vote` < 0
    ) AS `stat_comment_dislikes`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
    ) AS `stat_forum_posts_total`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_deleted` IS NOT NULL
    ) AS `stat_forum_posts_deleted`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_edited` IS NOT NULL
    ) AS `stat_forum_posts_edited`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_parse` = 0
    ) AS `stat_forum_posts_plain`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_parse` = 1
    ) AS `stat_forum_posts_bbcode`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_parse` = 2
    ) AS `stat_forum_posts_markdown`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `post_display_signature` != 0
    ) AS `stat_forum_posts_signature`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
    ) AS `stat_forum_topics_total`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_type` = 0
    ) AS `stat_forum_topics_normal`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_type` = 1
    ) AS `stat_forum_topics_pinned`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_type` = 2
    ) AS `stat_forum_topics_announce`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_type` = 3
    ) AS `stat_forum_topics_global_announce`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_deleted` IS NOT NULL
    ) AS `stat_forum_topics_deleted`,
    (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `topic_locked` IS NOT NULL
    ) AS `stat_forum_topics_locked`,
    (
        SELECT COUNT(*)
        FROM `msz_ip_blacklist`
    ) AS `stat_blacklist`,
    (
        SELECT COUNT(*)
        FROM `msz_login_attempts`
    ) AS `stat_login_attempts_total`,
    (
        SELECT COUNT(*)
        FROM `msz_login_attempts`
        WHERE `attempt_success` = 0
    ) AS `stat_login_attempts_failed`,
    (
        SELECT COUNT(`session_id`)
        FROM `msz_sessions`
    ) AS `stat_user_sessions`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_users_password_resets`
    ) AS `stat_user_password_resets`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_user_relations`
    ) AS `stat_user_relations`,
    (
        SELECT COUNT(`warning_id`)
        FROM `msz_user_warnings`
        WHERE `warning_type` != 0
    ) AS `stat_user_warnings`
')->fetch();

if(!empty($_GET['poll'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($statistics);
    return;
}

Template::render('manage.general.overview', [
    'statistics' => $statistics,
]);
