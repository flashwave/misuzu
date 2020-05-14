<?php
namespace Misuzu\Http;

use InvalidArgumentException;
use Misuzu\Template;
use Misuzu\Stream;

class HttpResponseMessage extends HttpMessage {
    private $statusCode = 0;
    private $reasonPhrase = '';

    public function __construct(int $statusCode = 200, string $reasonPhrase = '', array $headers = [], string $version = '1.1') {
        parent::__construct($headers, $version);
        $this->setStatusCode($statusCode)->setReasonPhrase($reasonPhrase);
    }

    public function getStatusCode() {
        return $this->statusCode;
    }
    public function setStatusCode(int $code): self {
        if($code < 100 || $code > 599)
            throw new InvalidArgumentException('Invalid status code.');
        $this->statusCode = $code;
        return $this;
    }
    public function getReasonPhrase() {
        return !empty($this->reasonPhrase) ? $this->reasonPhrase : (
            array_key_exists($statusCode = $this->getStatusCode(), self::PHRASES) ? self::PHRASES[$statusCode] : ''
        );
    }
    public function setReasonPhrase(string $reasonPhrase): self {
        $this->reasonPhrase = $reasonPhrase;
        return $this;
    }

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
        if(empty($this->getContentType()))
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

    // https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
    private const PHRASES = [
        // 1xx: Informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',

        // 2xx: Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',

        // 3xx: Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        // 4xx: Client Error
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        // 5xx: Server Error
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];
}
