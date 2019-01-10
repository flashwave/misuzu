<?php
require_once '../misuzu.php';

$acceptedProtocols = ['http', 'https'];
$acceptedMimeTypes = [
    'image/png', 'image/jpeg', 'image/bmp', 'image/x-bmp', 'image/gif', 'image/svg', 'image/svg+xml', 'image/tiff', 'image/tiff-fx', 'image/webp',
    'video/mp4', 'video/webm', 'video/x-msvideo', 'video/vnd.avi', 'video/msvideo', 'video/avi', 'video/mpeg', 'video/ogg',
    'audio/aac', 'audio/aacp', 'audio/3gpp', 'audio/3gpp2', 'audio/mp4', 'audio/mp4a-latm', 'audio/mpeg4-generic',
    'audio/ogg', 'audio/mp3', 'audio/mpeg', 'audio/mpa', 'audio/mpa-robust',
    'audio/wav', 'audio/vnd.wave', 'audio/wave', 'audio/x-wav',  'audio/webm', 'audio/x-flac', 'audio/flac',
];

header('Cache-Control: max-age=600');

$splitPath = explode('/', $_SERVER['PATH_INFO'] ?? '', 3);
$proxyHash = $splitPath[1] ?? '';
$proxyUrl = $splitPath[2] ?? '';

if (empty($proxyHash) || empty($proxyUrl)) {
    echo render_error(400);
    return;
}

$proxyUrlDecoded = base64url_decode($proxyUrl);
$parsedUrl = parse_url($proxyUrlDecoded);

if (empty($parsedUrl['scheme'])
    || empty($parsedUrl['host'])
    || !in_array($parsedUrl['scheme'], $acceptedProtocols, true)) {
    echo render_error(400);
    return;
}

if (!config_get_default(false, 'Proxy', 'enabled')) {
    header('Location: ' . $proxyUrlDecoded);
    return;
}

$proxySecret = config_get_default('insecure', 'Proxy', 'secret_key');
$expectedHash = hash_hmac('sha256', $proxyUrl, $proxySecret);

if (!hash_equals($expectedHash, $proxyHash)) {
    echo render_error(400);
    return;
}

$curl = curl_init($proxyUrlDecoded);
curl_setopt_array($curl, [
    CURLOPT_CERTINFO => false,
    CURLOPT_FAILONERROR => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TCP_FASTOPEN => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_MAXREDIRS => 4,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible) Misuzu/' . git_tag(),
]);
$curlBody = curl_exec($curl);
curl_close($curl);

$entityTag = '"' . hash('sha256', $curlBody) . '"';

if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strtolower($_SERVER['HTTP_IF_NONE_MATCH']) === $entityTag) {
    http_response_code(304);
    return;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$fileMime = strtolower(finfo_buffer($finfo, $curlBody));
finfo_close($finfo);

if (!in_array($fileMime, $acceptedMimeTypes, true)) {
    echo render_error(404);
    return;
}

$fileSize = strlen($curlBody);
$fileName = basename($parsedUrl['path'] ?? "proxied-image-{$expectedHash}");

header("Content-Type: {$fileMime}");
header("Content-Length: {$fileSize}");
header("Content-Disposition: inline; filename=\"{$fileName}\"");
header("ETag: {$entityTag}");

echo $curlBody;
