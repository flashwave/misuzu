<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;
use Misuzu\GitInfo;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\Assets\StaticUserImageAsset;
use Misuzu\Users\Assets\UserImageAssetInterface;
use Misuzu\Users\Assets\UserAssetScalableInterface;

final class AssetsHandler extends Handler {
    private const TYPES = [
        'js' => [
            'root' => MSZ_ROOT . '/assets/js',
            'mime' => 'application/javascript; charset=utf-8',
        ],
        'css' => [
            'root' => MSZ_ROOT . '/assets/css',
            'mime' => 'text/css; charset=utf-8',
        ],
    ];

    public function __construct() {
        $GLOBALS['misuzuBypassLockdown'] = true;
        parent::__construct();
    }

    private static function recurse(string $dir): string {
        $str = '';
        $dir = rtrim(realpath($dir), '/') . '/*';
        $dirs = [];

        foreach(glob($dir) as $path) {
            if(is_dir($path)) {
                $dirs[] = $path;
                continue;
            }

            if(MSZ_DEBUG)
                $str .= "/*** {$path} ***/\n";
            $str .= trim(file_get_contents($path));
            $str .= "\n\n";
        }

        foreach($dirs as $path)
            $str .= self::recurse($path);

        return $str;
    }

    public function serveComponent(HttpResponse $response, HttpRequest $request, string $name, string $type) {
        $entityTag = sprintf('W/"%s.%s/%s"', $name, $type, GitInfo::hash());

        if(!MSZ_DEBUG && $name === 'debug')
            return 404;
        if(!MSZ_DEBUG && $request->getHeaderLine('If-None-Match') === $entityTag)
            return 304;

        if(array_key_exists($type, self::TYPES)) {
            $type = self::TYPES[$type];
            $path = ($type['root'] ?? '') . '/' . $name;

            if(is_dir($path)) {
                $response->setHeader('Content-Type', $type['mime'] ?? 'application/octet-stream');
                $response->setHeader('Cache-Control', MSZ_DEBUG ? 'no-cache' : 'must-revalidate');
                $response->setHeader('ETag', $entityTag);
                return self::recurse($path);
            }
        }
    }

    private function canViewAsset(HttpRequest $request, User $assetUser): bool {
        return !$assetUser->isBanned() || (
            User::hasCurrent()
            && parse_url($request->getHeaderLine('Referer'), PHP_URL_PATH) === url('user-profile')
            && perms_check_user(MSZ_PERMS_USER, User::getCurrent()->getId(), MSZ_PERM_USER_MANAGE_USERS)
        );
    }

    private function serveUserAsset(HttpResponse $response, HttpRequest $request, UserImageAssetInterface $assetInfo): void {
        $contentType = $assetInfo->getMimeType();
        $publicPath = $assetInfo->getPublicPath();
        $fileName = $assetInfo->getFileName();

        if($assetInfo instanceof UserAssetScalableInterface) {
            $dimensions = (int)($request->getQueryParam('res', FILTER_SANITIZE_NUMBER_INT) ?? $request->getQueryParam('r', FILTER_SANITIZE_NUMBER_INT));

            if($dimensions > 0) {
                $assetInfo->ensureScaledExists($dimensions);
                $contentType = $assetInfo->getScaledMimeType($dimensions);
                $publicPath = $assetInfo->getPublicScaledPath($dimensions);
                $fileName = $assetInfo->getScaledFileName($dimensions);
            }
        }

        $response->setHeader('X-Accel-Redirect', $publicPath);
        $response->setHeader('Content-Type', $contentType);
        $response->setHeader('Content-Disposition', sprintf('inline; filename="%s"', $fileName));
    }

    public function serveAvatar(HttpResponse $response, HttpRequest $request, int $userId) {
        $assetInfo = new StaticUserImageAsset(MSZ_PUBLIC . '/images/no-avatar.png', MSZ_PUBLIC);

        try {
            $userInfo = User::byId($userId);

            if(!$this->canViewAsset($request, $userInfo)) {
                $assetInfo = new StaticUserImageAsset(MSZ_PUBLIC . '/images/banned-avatar.png', MSZ_PUBLIC);
            } elseif($userInfo->hasAvatar()) {
                $assetInfo = $userInfo->getAvatarInfo();
            }
        } catch(UserNotFoundException $ex) {}

        $this->serveUserAsset($response, $request, $assetInfo);
    }

    public function serveProfileBackground(HttpResponse $response, HttpRequest $request, int $userId) {
        try {
            $userInfo = User::byId($userId);
        } catch(UserNotFoundException $ex) {}

        if(empty($userInfo) || !$userInfo->hasBackground() || !$this->canViewAsset($request, $userInfo)) {
            $response->setText('');
            return 404;
        }

        $this->serveUserAsset($response, $request, $userInfo->getBackgroundInfo());
    }

    public function serveLegacy(HttpResponse $response, HttpRequest $request) {
        $assetUserId = (int)$request->getQueryParam('u', FILTER_SANITIZE_NUMBER_INT);

        switch($request->getQueryParam('m')) {
            case 'avatar':
                $this->serveAvatar($response, $request, $assetUserId);
                return;
            case 'background':
                $this->serveProfileBackground($response, $request, $assetUserId);
                return;
        }

        $response->setText('');
        return 404;
    }
}
