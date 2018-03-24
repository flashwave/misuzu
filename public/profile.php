<?php
use Misuzu\IO\File;
use Misuzu\IO\FileStream;
use Misuzu\Users\User;

require_once __DIR__ . '/../misuzu.php';

$user_id = (int)($_GET['u'] ?? 0);
$mode = (string)($_GET['m'] ?? 'view');
$profile_user = User::find($user_id);

switch ($mode) {
    case 'avatar':
        $avatar_filename = $app->getPath(
            $app->config->get('Avatar', 'default_path', 'string', 'public/images/no-avatar.png')
        );

        if ($profile_user !== null) {
            $user_avatar = "{$profile_user->user_id}.msz";
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
        }

        header('Content-Type: ' . mime_content_type($avatar_filename));
        echo File::readToEnd($avatar_filename);
        break;

    case 'view':
    default:
        if ($profile_user === null) {
            http_response_code(404);
            echo $app->templating->render('user.notfound');
            break;
        }

        $app->templating->var('profile', $profile_user);
        echo $app->templating->render('user.view');
        break;
}
