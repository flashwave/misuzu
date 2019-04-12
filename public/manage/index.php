<?php
require_once '../../misuzu.php';

$generalPerms = perms_get_user(MSZ_PERMS_GENERAL, user_session_current('user_id', 0));

switch ($_GET['v'] ?? null) {
    default:
    case 'overview':
        $statistics = db_fetch(db_query('
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
                    SELECT COUNT(`log_id`)
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
                    SELECT COUNT(`attempt_id`)
                    FROM `msz_login_attempts`
                ) AS `stat_login_attempts_total`,
                (
                    SELECT COUNT(`attempt_id`)
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
        '));

        if (!empty($_GET['poll'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($statistics);
            return;
        }

        echo tpl_render('manage.general.overview', [
            'statistics' => $statistics,
        ]);
        break;

    case 'logs':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_VIEW_LOGS)) {
            echo render_error(403);
            break;
        }

        $logsPagination = pagination_create(audit_log_count(), 50);
        $logsOffset = pagination_offset($logsPagination, pagination_param());

        if (!pagination_is_valid_offset($logsOffset)) {
            echo render_error(404);
            break;
        }

        $logs = audit_log_list($logsOffset, $logsPagination['range']);

        echo tpl_render('manage.general.logs', [
            'global_logs' => $logs,
            'global_logs_pagination' => $logsPagination,
            'global_logs_strings' => MSZ_AUDIT_LOG_STRINGS,
        ]);
        break;

    case 'emoticons':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.emoticons');
        break;

    case 'settings':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_SETTINGS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.settings');
        break;

    case 'blacklist':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_BLACKLIST)) {
            echo render_error(403);
            break;
        }

        $notices = [];

        if (!empty($_POST)) {
            if (!csrf_verify('ip_blacklist', $_POST['csrf'] ?? '')) {
                $notices[] = 'Verification failed.';
            } else {
                header(csrf_http_header('ip_blacklist'));

                if (!empty($_POST['blacklist']['remove']) && is_array($_POST['blacklist']['remove'])) {
                    foreach ($_POST['blacklist']['remove'] as $cidr) {
                        if (!ip_blacklist_remove($cidr)) {
                            $notices[] = sprintf('Failed to remove "%s" from the blacklist.', $cidr);
                        }
                    }
                }

                if (!empty($_POST['blacklist']['add']) && is_string($_POST['blacklist']['add'])) {
                    $cidrs = explode("\n", $_POST['blacklist']['add']);

                    foreach ($cidrs as $cidr) {
                        $cidr = trim($cidr);

                        if (empty($cidr)) {
                            continue;
                        }

                        if (!ip_blacklist_add($cidr)) {
                            $notices[] = sprintf('Failed to add "%s" to the blacklist.', $cidr);
                        }
                    }
                }
            }
        }

        echo tpl_render('manage.general.blacklist', [
            'notices' => $notices,
            'blacklist' => ip_blacklist_list(),
        ]);
        break;
}
