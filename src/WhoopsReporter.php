<?php
namespace Misuzu;

use Whoops\Exception\Formatter;
use Whoops\Handler\Handler;

final class WhoopsReporter extends Handler {
    private $reportUrl;
    private $reportSign;

    public function __construct(?string $reportUrl = null, ?string $reportSign = null) {
        $this->setReportInfo($reportUrl, $reportSign);
    }

    public function setReportInfo(?string $reportUrl = null, ?string $reportSign = null): void {
        $this->reportUrl = $reportUrl;
        $this->reportSign = $reportSign;
    }

    public function handle() {
        echo $this->html(
            $this->report()
            ? 'Information about this error has been sent to the devs.'
            : 'Report what you were trying to a developer.'
        );

        return Handler::QUIT;
    }

    public function contentType(): string {
        return 'text/html';
    }

    public function html(string $text): string {
        return <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Something broke!</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/css/style.css" rel="stylesheet">
    </head>
    <body class="main" style="justify-content: center; --site-max-width: 400px">
        <div class="main__wrapper" style="flex: 0 0 auto">
            <div class="container">
                <div class="container__title">
                    <div class="container__title__background"></div>
                    <div class="container__title__text"><i class="fas fa-bug fa-fw"></i> Something broke!</div>
                </div>
                <div class="container__content">
                    <p>{$text}</p>
                </div>
            </div>
        </div>
    </body>
</html>
HTML;
    }

    private function report(): bool {
        if(!mb_strlen($this->reportUrl) || !($curl = curl_init($this->reportUrl))) {
            return false;
        }

        $json = json_encode([
            'git' => [
                'tag' => git_tag(),
                'hash' => git_commit_hash(true),
            ],
            'misuzu' => [
                'trace_txt' => $this->getException()->getTraceAsString(),
                'directory' => MSZ_ROOT,
            ],
            'exception' => Formatter::formatExceptionAsDataArray(
                $this->getInspector(),
                $this
            ),
        ]);

        $headers = [
            'Content-Type: application/json;charset=utf-8',
        ];

        if(mb_strlen($this->reportSign)) {
            $headers[] = 'X-Misuzu-Signature: sha256=' . hash_hmac('sha256', $json, $this->reportSign);
        }

        $setOpts = curl_setopt_array($curl, [
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
        ]);

        if(!$setOpts) {
            return false;
        }

        return curl_exec($curl) !== false;
    }
}
