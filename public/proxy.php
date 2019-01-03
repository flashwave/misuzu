<?php
require_once '../misuzu.php';

$acceptedProtocols = ['http', 'https'];
$acceptedMimeTypes = [
    'image/png', 'image/jpeg', 'image/bmp', 'image/gif', 'image/svg', 'image/svg+xml', 'image/tiff', 'image/webp',
    'video/mp4', 'video/webm', 'video/x-msvideo', 'video/mpeg', 'video/ogg',
    'audio/aac', 'audio/ogg', 'audio/mp3', 'audio/mpeg', 'audio/wav',  'audio/webm',
];

header('Cache-Control: max-age=600');

$proxyUrl = rawurldecode($_GET['u'] ?? '');
$proxyHash = $_GET['h'] ?? '';

if (empty($proxyHash) || empty($proxyUrl)) {
    echo render_error(400);
    return;
}

$parsedUrl = parse_url($proxyUrl);

if (empty($parsedUrl['scheme'])
    || empty($parsedUrl['host'])
    || !in_array($parsedUrl['scheme'], $acceptedProtocols, true)) {
    echo render_error(400);
    return;
}

if (!config_get_default(false, 'Proxy', 'enabled')) {
    header('Location: ' . $proxyUrl);
    return;
}

$proxySecret = config_get_default('insecure', 'Proxy', 'secret_key');
$expectedHash = hash_hmac('sha256', $proxyUrl, $proxySecret);

if (!hash_equals($expectedHash, $proxyHash)) {
    echo render_error(400);
    return;
}

$curl = curl_init($proxyUrl);
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

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$fileMime = finfo_buffer($finfo, $curlBody);
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

echo $curlBody;
