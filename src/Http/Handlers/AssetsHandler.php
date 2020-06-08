<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;
use Misuzu\GitInfo;

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

    public static function view(HttpResponse $response, HttpRequest $request, string $name, string $type) {
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
}
