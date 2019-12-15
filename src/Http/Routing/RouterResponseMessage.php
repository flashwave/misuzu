<?php
namespace Misuzu\Http\Routing;

use Misuzu\Template;
use Misuzu\Http\HttpResponseMessage;
use Misuzu\Http\Stream;

class RouterResponseMessage extends HttpResponseMessage {
    public function getContentType(): string {
        return $this->getHeaderLine('Content-Type');
    }
    public function setContentType(string $type): self {
        $this->setHeader('Content-Type', $type);
        return $this;
    }

    public function setText(string $text): self {
        $body = $this->getBody();

        if($body !== null)
            $body->close();

        $this->setBody(Stream::create($text));
        return $this;
    }
    public function appendText(string $text): self {
        $body = $this->getBody();

        if($body === null)
            $this->setBody(Stream::create($text));
        else
            $body->write($text);
        return $this;
    }
    public function setHtml(string $content, bool $html = true): self {
        $this->setContentType('text/html; charset=utf-8');
        $this->setText($content);
        return $this;
    }
    public function setTemplate(string $file, array $vars = []): self {
        return $this->setHtml(Template::renderRaw($file, $vars));
    }
    public function setJson($content, int $options = 0): self {
        $this->setContentType('application/json; charset=utf-8');
        $this->setText(json_encode($content, $options));
        return $this;
    }

    public function redirect(string $path, bool $permanent = false, bool $xhr = false): self {
        $this->setStatusCode($permanent ? 301 : 302);
        $this->setHeader($xhr ? 'X-Misuzu-Location' : 'Location', $path);
        return $this;
    }

    public function getContents(): string {
        return (string)($this->getBody() ?? '');
    }
}
