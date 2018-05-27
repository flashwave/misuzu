<?php
use Misuzu\Database;
use Misuzu\IO\File;

require_once __DIR__ . '/../misuzu.php';

$user_id = (int)($_GET['u'] ?? 0);
$mode = (string)($_GET['m'] ?? 'view');

switch ($mode) {
    case 'avatar':
        $avatar_filename = $app->getPath(
            $app->getConfig()->get('Avatar', 'default_path', 'string', 'public/images/no-avatar.png')
        );

        $user_avatar = "{$user_id}.msz";
        $cropped_avatar = $app->getStore('avatars/200x200')->filename($user_avatar);

        if (File::exists($cropped_avatar)) {
            $avatar_filename = $cropped_avatar;
        } else {
            $original_avatar = $app->getStore('avatars/original')->filename($user_avatar);

            if (File::exists($original_avatar)) {
                try {
                    File::writeAll(
                        $cropped_avatar,
                        crop_image_centred_path($original_avatar, 200, 200)->getImagesBlob()
                    );

                    $avatar_filename = $cropped_avatar;
                } catch (Exception $ex) {
                }
            }
        }

        header('Content-Type: ' . mime_content_type($avatar_filename));
        echo File::readToEnd($avatar_filename);
        break;

    case 'view':
    default:
        $templating = $app->getTemplating();

        $getProfile = Database::connection()->prepare('
            SELECT
                u.*,
                COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
                COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `display_colour`,
                (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics` as t
                    WHERE t.`user_id` = u.`user_id`
                ) as `forum_topic_count`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts` as p
                    WHERE p.`user_id` = u.`user_id`
                ) as `forum_post_count`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON r.`role_id` = u.`display_role`
            WHERE `user_id` = :user_id
        ');
        $getProfile->bindValue('user_id', $user_id);
        $profile = $getProfile->execute() ? $getProfile->fetch() : [];

        if (!$profile) {
            http_response_code(404);
            echo $templating->render('user.notfound');
            break;
        }

        $templating->vars(compact('profile'));
        echo $templating->render('user.view');
        break;
}
