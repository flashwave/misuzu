<?php
$userAssetsMode = (string)($_GET['m'] ?? null);
$misuzuBypassLockdown = $userAssetsMode === 'avatar';

require_once '../misuzu.php';

$userId = (int)($_GET['u'] ?? 0);
$userExists = user_exists($userId);

$canViewImages = !$userExists
    || !user_warning_check_expiration($userId, MSZ_WARN_BAN)
    || (
        parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) === url('user-profile')
        && perms_check_user(MSZ_PERMS_USER, user_session_current('user_id', 0), MSZ_PERM_USER_MANAGE_USERS)
    );

switch ($userAssetsMode) {
    case 'avatar':
        if (!$canViewImages) {
            $filename = config_get_default(MSZ_ROOT . '/public/images/banned-avatar.png', 'Avatar', 'banned_path');
            break;
        }

        $filename = config_get_default(MSZ_ROOT . '/public/images/no-avatar.png', 'Avatar', 'default_path');

        if (!$userExists) {
            break;
        }

        $dimensions = 200;
        $avatarFilename = sprintf('%d.msz', $userId);
        $avatarStorage = sprintf('%1$s/avatars/%2$dx%2$d', MSZ_STORAGE, $dimensions);
        $avatarCropped = sprintf('%s/%s', $avatarStorage, $avatarFilename);

        if (is_file($avatarCropped)) {
            $filename = $avatarCropped;
        } else {
            $avatarOriginal = sprintf('%s/avatars/original/%s', MSZ_STORAGE, $avatarFilename);

            if (is_file($avatarOriginal)) {
                try {
                    mkdirs($avatarStorage, true);

                    file_put_contents(
                        $avatarCropped,
                        crop_image_centred_path($avatarOriginal, $dimensions, $dimensions)->getImagesBlob(),
                        LOCK_EX
                    );

                    $filename = $avatarCropped;
                } catch (Exception $ex) {
                }
            }
        }
        break;

    case 'background':
        if (!$canViewImages && !$userExists) {
            break;
        }

        $backgroundStorage = sprintf('%s/backgrounds/original', MSZ_STORAGE);
        $filename = sprintf('%s/%d.msz', $backgroundStorage, $userId);
        mkdirs($backgroundStorage, true);
        break;
}

if (empty($filename) || !is_file($filename)) {
    http_response_code(404);
    return;
}

$entityTag = sprintf('W/"{%s-%d-%d}"', $userAssetsMode, $userId, filemtime($filename));

if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $entityTag) {
    http_response_code(304);
    return;
}

http_response_code(200);
header(sprintf('Content-Type: %s', mime_content_type($filename)));
header(sprintf('ETag: %s', $entityTag));
echo file_get_contents($filename);
