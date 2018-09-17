<?php
use Misuzu\Database;
use Misuzu\IO\File;

require_once __DIR__ . '/../misuzu.php';

$user_id = (int)($_GET['u'] ?? 0);
$mode = (string)($_GET['m'] ?? null);

switch ($mode) {
    case 'avatar':
        $avatar_filename = $app->getDefaultAvatar();
        $user_avatar = "{$user_id}.msz";
        $cropped_avatar = build_path(
            create_directory(build_path($app->getStoragePath(), 'avatars/200x200')),
            $user_avatar
        );

        if (is_file($cropped_avatar)) {
            $avatar_filename = $cropped_avatar;
        } else {
            $original_avatar = build_path($app->getStoragePath(), 'avatars/original', $user_avatar);

            if (is_file($original_avatar)) {
                try {
                    file_put_contents(
                        $cropped_avatar,
                        crop_image_centred_path($original_avatar, 200, 200)->getImagesBlob(),
                        LOCK_EX
                    );

                    $avatar_filename = $cropped_avatar;
                } catch (Exception $ex) {
                }
            }
        }

        header('Content-Type: ' . mime_content_type($avatar_filename));
        echo file_get_contents($avatar_filename);
        break;

    case 'background':
        $user_background = build_path(
            create_directory(build_path($app->getStoragePath(), 'backgrounds/original')),
            "{$user_id}.msz"
        );

        if (!is_file($user_background)) {
            echo render_error(404);
            break;
        }

        header('Content-Type: ' . mime_content_type($user_background));
        echo file_get_contents($user_background);
        break;

    default:
        $getProfile = Database::prepare('
            SELECT
                u.*,
                COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics`
                    WHERE `user_id` = u.`user_id`
                ) as `forum_topic_count`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `user_id` = u.`user_id`
                ) as `forum_post_count`,
                (
                    SELECT COUNT(`change_id`)
                    FROM `msz_changelog_changes`
                    WHERE `user_id` = u.`user_id`
                ) as `changelog_count`,
                (
                    SELECT COUNT(`comment_id`)
                    FROM `msz_comments_posts`
                    WHERE `user_id` = u.`user_id`
                ) as `comments_count`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON r.`role_id` = u.`display_role`
            WHERE `user_id` = :user_id
        ');
        $getProfile->bindValue('user_id', $user_id);
        $profile = $getProfile->execute() ? $getProfile->fetch() : [];

        if (!$profile) {
            http_response_code(404);
            echo tpl_render('user.notfound');
            break;
        }

        tpl_vars([
            'profile' => $profile,
            'has_background' => is_file(build_path($app->getStoragePath(), 'backgrounds/original', "{$profile['user_id']}.msz")),
        ]);
        echo tpl_render('user.profile');
        break;
}
