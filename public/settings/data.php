<?php
namespace Misuzu;

use ZipArchive;
use Misuzu\AuditLog;
use Misuzu\Users\User;
use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

if(!UserSession::hasCurrent()) {
    echo render_error(401);
    return;
}

function db_to_zip(ZipArchive $archive, int $userId, string $filename, string $query, int $params = 1): void {
    $prepare = DB::prepare($query);

    if($params < 2) {
        $prepare->bind('user_id', $userId);
    } else {
        for($i = 1; $i <= $params; $i++) {
            $prepare->bind('user_id_' . $i, $userId);
        }
    }

    $archive->addFromString($filename, json_encode($prepare->fetchAll(), JSON_PRETTY_PRINT));
}

$errors = [];
$currentUser = User::getCurrent();
$currentUserId = $currentUser->getId();

if(isset($_POST['action']) && is_string($_POST['action'])) {
    if(isset($_POST['password']) && is_string($_POST['password'])
        && $currentUser->checkPassword($_POST['password'] ?? '')) {
        switch($_POST['action']) {
            case 'data':
                AuditLog::create(AuditLog::PERSONAL_DATA_DOWNLOAD);

                $timeStamp = floor(time() / 3600) * 3600;
                $fileName = sprintf('msz-user-data-%d-%d.zip', $currentUserId, $timeStamp);
                $filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
                $archive = new ZipArchive;

                if(!is_file($filePath)) {
                    if($archive->open($filePath, ZipArchive::CREATE | ZIPARCHIVE::OVERWRITE) === true) {
                        db_to_zip($archive, $currentUserId, 'audit_log.json',               'SELECT *, INET6_NTOA(`log_ip`) AS `log_ip`                                                                                             FROM `msz_audit_log`                WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'auth_tfa.json',                'SELECT *                                                                                                                               FROM `msz_auth_tfa`                 WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'changelog_changes.json',       'SELECT *                                                                                                                               FROM `msz_changelog_changes`        WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'comments_posts.json',          'SELECT *                                                                                                                               FROM `msz_comments_posts`           WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'comments_votes.json',          'SELECT *                                                                                                                               FROM `msz_comments_votes`           WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_permissions.json',       'SELECT *                                                                                                                               FROM `msz_forum_permissions`        WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_polls_answers.json',     'SELECT *                                                                                                                               FROM `msz_forum_polls_answers`      WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_posts.json',             'SELECT *, INET6_NTOA(`post_ip`) AS `post_ip`                                                                                           FROM `msz_forum_posts`              WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_topics.json',            'SELECT *                                                                                                                               FROM `msz_forum_topics`             WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_topics_priority.json',   'SELECT *                                                                                                                               FROM `msz_forum_topics_priority`    WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'forum_topics_track.json',      'SELECT *                                                                                                                               FROM `msz_forum_topics_track`       WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'login_attempts.json',          'SELECT *, INET6_NTOA(`attempt_ip`) AS `attempt_ip`                                                                                     FROM `msz_login_attempts`           WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'news_posts.json',              'SELECT *                                                                                                                               FROM `msz_news_posts`               WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'permissions.json',             'SELECT *                                                                                                                               FROM `msz_permissions`              WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'profile_fields_values.json',   'SELECT *                                                                                                                               FROM `msz_profile_fields_values`    WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'sessions.json',                'SELECT *, INET6_NTOA(`session_ip`) AS `session_ip`, INET6_NTOA(`session_ip_last`) AS `session_ip_last`                                 FROM `msz_sessions`                 WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'users.json',                   'SELECT *, NULL AS `password`, NULL AS `user_totp_key`, INET6_NTOA(`register_ip`) AS `register_ip`, INET6_NTOA(`last_ip`) AS `last_ip`  FROM `msz_users`                    WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'users_password_resets.json',   'SELECT *, INET6_NTOA(`reset_ip`) AS `reset_ip`                                                                                         FROM `msz_users_password_resets`    WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'user_chat_tokens.json',        'SELECT *                                                                                                                               FROM `msz_user_chat_tokens`         WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'user_relations.json',          'SELECT *                                                                                                                               FROM `msz_user_relations`           WHERE `user_id` = :user_id_1 OR `subject_id` = :user_id_2', 2);
                        db_to_zip($archive, $currentUserId, 'user_roles.json',              'SELECT *                                                                                                                               FROM `msz_user_roles`               WHERE `user_id` = :user_id');
                        db_to_zip($archive, $currentUserId, 'user_warnings.json',           'SELECT *, INET6_NTOA(`user_ip`) AS `user_ip`, NULL AS `issuer_id`, NULL AS `issuer_ip`, NULL AS `warning_note_private`                 FROM `msz_user_warnings`            WHERE `user_id` = :user_id');

                        $archive->close();
                    } else {
                        $errors[] = 'Something went wrong while creating your account archive.';
                        break;
                    }
                }

                header('Content-Type: application/zip');
                header(sprintf('Content-Disposition: inline; filename="%s"', $fileName));
                echo file_get_contents($filePath);
                return;

            case 'deactivate':
                // deactivation
                break;
        }
    } else {
        $errors[] = 'Incorrect password.';
    }
}

Template::render('settings.data', [
    'errors' => $errors,
]);
