<?php
namespace Misuzu\Http\Headers;

// specially parsed headers should inherit this and provide their own specific things
class HttpHeader {
    private $name = '';
    protected $lines = [];

    public function __construct(string $name, $line = null) {
        $this->name = $name;
        if($line !== null)
            $this->set($line);
    }

    public function getName(): string {
        return $this->name;
    }
    public function isMultiline(): bool {
        return true;
    }
    public function getLines(): array {
        return $this->lines;
    }

    // make sure you only call these two with shit that can be cast to string or you will implode
    // expect InvalidArgumentError throws for subclasses
    public function set($line): void {
        $this->lines = [$line];
    }
    public function append($line): void {
        $this->lines[] = $line;
    }

    public function getHeaders(): array {
        $headers = [];

        if($this->isMultiline()) {
            foreach($this->getLines() as $line)
                $headers[] = sprintf('%s: %s', $this->getName(), $line);
        } else
            $headers[] = sprintf('%s: %s', $this->getName(), implode(', ', $this->getLines()));

        return $headers;
    }

    public static function create(string $name, $line = null): HttpHeader {
        switch($name) {
            case 'Accept':
            case 'Accept-Charset':
            case 'Accept-Language':
            case 'Accept-Encoding':
            case 'TE':
                return new AcceptHttpHeader($name, $line);
            default:
                return new static($name, $line);
        }
    }
}
