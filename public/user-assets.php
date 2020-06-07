<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\Assets\StaticUserImageAsset;
use Misuzu\Users\Assets\UserAssetScalableInterface;

$assetMode   = (string)filter_input(INPUT_GET, 'm', FILTER_SANITIZE_STRING);
$assetUserId =    (int)filter_input(INPUT_GET, 'u', FILTER_SANITIZE_NUMBER_INT);
$assetDims   =         filter_input(INPUT_GET, 'r', FILTER_SANITIZE_NUMBER_INT);

$misuzuBypassLockdown = $assetMode === 'avatar';

require_once '../misuzu.php';

try {
    $assetUser = User::byId($assetUserId);
} catch(UserNotFoundException $ex) {}

$assetVisible = !isset($assetUser) || !$assetUser->isBanned() || (
    parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) === url('user-profile')
    && User::hasCurrent() && perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_USERS)
);

switch($assetMode) {
    case 'avatar':
        if(!$assetVisible) {
            $assetInfo = new StaticUserImageAsset(MSZ_PUBLIC . '/images/banned-avatar.png', MSZ_PUBLIC);
            break;
        }

        $assetInfo = new StaticUserImageAsset(MSZ_PUBLIC . '/images/no-avatar.png', MSZ_PUBLIC);

        if(!isset($assetUser) || !$assetUser->hasAvatar())
            break;

        $assetInfo = $assetUser->getAvatarInfo();
        break;

    case 'background':
        if(!$assetVisible || !isset($assetUser) || !$assetUser->hasBackground())
            break;
        $assetInfo = $assetUser->getBackgroundInfo();
        break;
}

if(!isset($assetInfo) || !$assetInfo->isPresent()) {
    http_response_code(404);
    return;
}

$contentType = $assetInfo->getMimeType();
$publicPath = $assetInfo->getPublicPath();
$fileName = $assetInfo->getFileName();

if($assetDims > 0 && $assetInfo instanceof UserAssetScalableInterface) {
    $assetInfo->ensureScaledExists($assetDims);

    $publicPath = $assetInfo->getPublicScaledPath($assetDims);
    $fileName = $assetInfo->getScaledFileName($assetDims);
}

header(sprintf('X-Accel-Redirect: %s', $publicPath));
header(sprintf('Content-Type: %s', $contentType));
header(sprintf('Content-Disposition: inline; filename="%s"', $fileName));
