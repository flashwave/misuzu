<?php
namespace Misuzu\Http\Handlers;

final class InfoHandler extends Handler {
    public function index(Response $response): void {
        $response->setTemplate('info.index');
    }

    public function redir(Response $response, Request $request, string $name = ''): void {
        $response->redirect(url('info', ['title' => $name]), true);
    }

    public function page(Response $response, Request $request, string $name) {
        $document = [
            'content' => '',
            'title' => '',
        ];

        $isMisuzuDoc = $name === 'misuzu' || starts_with($name, 'misuzu/');

        if($isMisuzuDoc) {
            $filename = substr($name, 7);
            $filename = empty($filename) ? 'README' : strtoupper($filename);

            if($filename !== 'README') {
                $titleSuffix = ' - Misuzu Project';
            }
        } else {
            $filename = strtolower($name);
        }

        if(!preg_match('#^([A-Za-z0-9_]+)$#', $filename)) {
            return 404;
        }

        if($filename !== 'LICENSE') {
            $filename .= '.md';
        }

        $filename = MSZ_ROOT . ($isMisuzuDoc ? '/' : '/docs/') . $filename;
        $document['content'] = is_file($filename) ? file_get_contents($filename) : '';

        if(empty($document['content'])) {
            return 404;
        }

        if(empty($document['title'])) {
            if(starts_with($document['content'], '# ')) {
                $titleOffset = strpos($document['content'], "\n");
                $document['title'] = trim(substr($document['content'], 2, $titleOffset - 1));
                $document['content'] = substr($document['content'], $titleOffset);
            } else {
                $document['title'] = ucfirst(basename($filename));
            }

            if(!empty($titleSuffix)) {
                $document['title'] .= $titleSuffix;
            }
        }

        $response->setTemplate('info.view', [
            'document' => $document,
        ]);
    }
}
