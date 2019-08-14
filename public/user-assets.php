<?php
$userAssetsMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$misuzuBypassLockdown = $userAssetsMode === 'avatar';

require_once '../misuzu.php';

$userId = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$userExists = user_exists($userId);

$canViewImages = !$userExists
    || !user_warning_check_expiration($userId, MSZ_WARN_BAN)
    || (
        parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) === url('user-profile')
        && perms_check_user(MSZ_PERMS_USER, user_session_current('user_id', 0), MSZ_PERM_USER_MANAGE_USERS)
    );

switch($userAssetsMode) {
    case 'avatar':
        if(!$canViewImages) {
            $filename = config_get('avatar.banned', MSZ_CFG_STR, MSZ_ROOT . '/public/images/banned-avatar.png');
            break;
        }

        $filename = config_get('avatar.default', MSZ_CFG_STR, MSZ_ROOT . '/public/images/no-avatar.png');

        if(!$userExists) {
            break;
        }

        $dimensions = MSZ_USER_AVATAR_RESOLUTION_DEFAULT;

        // todo: find closest dimensions
        if(isset($_GET['r']) && is_string($_GET['r']) && ctype_digit($_GET['r'])) {
            $dimensions = user_avatar_resolution_closest((int)$_GET['r']);
        }

        $avatarFilename = sprintf('%d.msz', $userId);
        $avatarOriginal = sprintf('%s/avatars/original/%s', MSZ_STORAGE, $avatarFilename);

        if($dimensions === MSZ_USER_AVATAR_RESOLUTION_ORIGINAL) {
            $filename = $avatarOriginal;
            break;
        }

        $avatarStorage = sprintf('%1$s/avatars/%2$dx%2$d', MSZ_STORAGE, $dimensions);
        $avatarCropped = sprintf('%s/%s', $avatarStorage, $avatarFilename);

        if(is_file($avatarCropped)) {
            $filename = $avatarCropped;
        } else {
            if(is_file($avatarOriginal)) {
                try {
                    mkdirs($avatarStorage, true);

                    $avatarImage = new Imagick($avatarOriginal);
                    $avatarImage->setImageFormat($avatarImage->getNumberImages() > 1 ? 'gif' : 'png');
                    $avatarImage = $avatarImage->coalesceImages();

                    $avatarOriginalWidth = $avatarImage->getImageWidth();
                    $avatarOriginalHeight = $avatarImage->getImageHeight();

                    if($avatarOriginalWidth > $avatarOriginalHeight) {
                        $avatarWidth = $avatarOriginalWidth * $dimensions / $avatarOriginalHeight;
                        $avatarHeight = $dimensions;
                    } else {
                        $avatarWidth = $dimensions;
                        $avatarHeight = $avatarOriginalHeight * $dimensions / $avatarOriginalWidth;
                    }

                    do {
                        $avatarImage->resizeImage(
                            $avatarWidth,
                            $avatarHeight,
                            Imagick::FILTER_LANCZOS,
                            0.9
                        );

                        $avatarImage->cropImage(
                            $dimensions,
                            $dimensions,
                            ($avatarWidth - $dimensions) / 2,
                            ($avatarHeight - $dimensions) / 2
                        );

                        $avatarImage->setImagePage(
                            $dimensions,
                            $dimensions,
                            0,
                            0
                        );
                    } while($avatarImage->nextImage());

                    $avatarImage->deconstructImages()->writeImages($filename = $avatarCropped, true);
                } catch(Exception $ex) {}
            }
        }
        break;

    case 'background':
        if(!$canViewImages && !$userExists) {
            break;
        }

        $backgroundStorage = sprintf('%s/backgrounds/original', MSZ_STORAGE);
        $filename = sprintf('%s/%d.msz', $backgroundStorage, $userId);
        mkdirs($backgroundStorage, true);
        break;
}

if(empty($filename) || !is_file($filename)) {
    http_response_code(404);
    return;
}

$fileContents = file_get_contents($filename);
$entityTag = sprintf('W/"{%s}"', hash('sha256', $fileContents));

if(!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $entityTag) {
    http_response_code(304);
    return;
}

$finfo = finfo_open(FILEINFO_MIME);
$fmime = finfo_buffer($finfo, $fileContents);
finfo_close($finfo);

http_response_code(200);
header(sprintf('Content-Type: %s', $fmime));
header(sprintf('ETag: %s', $entityTag));
echo $fileContents;
