<?php
namespace Misuzu;

use Misuzu\Imaging\Image;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

$userAssetsMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$misuzuBypassLockdown = $userAssetsMode === 'avatar';

require_once '../misuzu.php';

try {
    $userInfo = User::byId((int)filter_input(INPUT_GET, 'u', FILTER_SANITIZE_NUMBER_INT));
    $userExists = true;
} catch(UserNotFoundException $ex) {
    $userExists = false;
}
$userId = $userExists ? $userInfo->getId() : 0;

$canViewImages = !$userExists
    || !$userInfo->isBanned()
    || (
        parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) === url('user-profile')
        && perms_check_user(MSZ_PERMS_USER, User::hasCurrent() ? User::getCurrent()->getId() : 0, MSZ_PERM_USER_MANAGE_USERS)
    );

switch($userAssetsMode) {
    case 'avatar':
        if(!$canViewImages) {
            $filename = Config::get('avatar.banned', Config::TYPE_STR, MSZ_ROOT . '/public/images/banned-avatar.png');
            break;
        }

        $filename = Config::get('avatar.default', Config::TYPE_STR, MSZ_ROOT . '/public/images/no-avatar.png');

        if(!$userExists)
            break;

        $dimensions = MSZ_USER_AVATAR_RESOLUTION_DEFAULT;
        if(isset($_GET['r']) && is_string($_GET['r']) && ctype_digit($_GET['r']))
            $dimensions = user_avatar_resolution_closest((int)$_GET['r']);

        $avatarFilename = sprintf('%d.msz', $userId);
        $avatarOriginal = sprintf('%s/avatars/original/%s', MSZ_STORAGE, $avatarFilename);

        if($dimensions === MSZ_USER_AVATAR_RESOLUTION_ORIGINAL) {
            $filename = $avatarOriginal;
            break;
        }

        $avatarStorage = sprintf('%1$s/avatars/%2$dx%2$d', MSZ_STORAGE, $dimensions);
        $avatarCropped = sprintf('%s/%s', $avatarStorage, $avatarFilename);
        $fileDisposition = sprintf('avatar-%d-%2$dx%2$d', $userId, $dimensions);

        if(is_file($avatarCropped)) {
            $filename = $avatarCropped;
        } else {
            if(is_file($avatarOriginal)) {
                try {
                    mkdirs($avatarStorage, true);

                    $avatarImage = Image::create($avatarOriginal);
                    $avatarImage->squareCrop($dimensions);
                    $avatarImage->save($filename = $avatarCropped);
                } catch(Exception $ex) {}
            }
        }
        break;

    case 'background':
        if(!$canViewImages && !$userExists)
            break;

        $backgroundStorage = sprintf('%s/backgrounds/original', MSZ_STORAGE);
        $fileDisposition = sprintf('background-%d', $userId);
        $filename = sprintf('%s/%d.msz', $backgroundStorage, $userId);
        mkdirs($backgroundStorage, true);
        break;
}

if(empty($filename) || !is_file($filename)) {
    http_response_code(404);
    return;
}

$contentType = mime_content_type($filename);

header(sprintf('X-Accel-Redirect: %s', str_replace(MSZ_STORAGE, '/msz-storage', $filename)));
header(sprintf('Content-Type: %s', $contentType));
if(isset($fileDisposition))
    header(sprintf('Content-Disposition: inline; filename="%s.%s"', $fileDisposition, explode('/', $contentType)[1]));
