<?php
use Misuzu\Database;

require_once '../misuzu.php';

$pathInfo = $_SERVER['PATH_INFO'] ?? '';

if (empty($pathInfo) || $pathInfo === '/') {
    echo tpl_render('info.index');
    return;
}

$document = [
    'content' => '',
    'title' => '',
];

$isMisuzuDoc = $pathInfo === '/misuzu' || starts_with($pathInfo, '/misuzu/');

if ($isMisuzuDoc) {
    $filename = substr($pathInfo, 8);
    $filename = empty($filename) ? 'README' : strtoupper($filename);

    if ($filename !== 'README') {
        $titleSuffix = ' - Misuzu Project';
    }
} else {
    $filename = strtolower(substr($pathInfo, 1));
}

if (!preg_match('#^([A-Za-z0-9_]+)$#', $filename)) {
    echo render_error(404);
    return;
}

if ($filename !== 'LICENSE') {
    $filename .= '.md';
}

$filename = MSZ_ROOT . ($isMisuzuDoc ? '/' : '/docs/') . $filename;
$document['content'] = is_file($filename) ? file_get_contents($filename) : '';

if (empty($document['content'])) {
    echo render_error(404);
    return;
}

if (empty($document['title'])) {
    if (starts_with($document['content'], '# ')) {
        $titleOffset = strpos($document['content'], "\n");
        $document['title'] = trim(substr($document['content'], 2, $titleOffset - 1));
        $document['content'] = substr($document['content'], $titleOffset);
    } else {
        $document['title'] = ucfirst(basename($filename));
    }

    if (!empty($titleSuffix)) {
        $document['title'] .= $titleSuffix;
    }
}

echo tpl_render('info.view', [
    'document' => $document,
]);
