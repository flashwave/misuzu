<?php
namespace Misuzu;

use InvalidArgumentException;

class MediaType implements HasQualityInterface {
    private $type = '';
    private $subtype = '';
    private $suffix = '';
    private $params = [];

    public function __construct(string $mediaType) {
        if(preg_match('#^([A-Za-z0-9\!\#\$%&\'\*\+\.\-_\{\}\|]+)/([A-Za-z0-9\!\#\$%&\'\*\+\.\-_\{\}\|]+)(?: ?; ?([A-Za-z0-9\!\#\$%&\'\*\+\.\-_\{\}\|\=; ]+))?$#', $mediaType, $matches) !== 1)
            throw new InvalidArgumentException('Invalid media type supplied.');

        $this->type = $matches[1];

        $subTypeSplit = explode('+', $matches[2], 2);
        $this->subtype = $subTypeSplit[0];
        if(isset($subTypeSplit[1]))
            $this->suffix = $subTypeSplit[1];

        if(isset($matches[3])) {
            $params = explode(';', $matches[3]);
            foreach($params as $param) {
                $parts = explode('=', trim($param), 2);
                if(!isset($parts[1]))
                    continue;
                $this->params[$parts[0]] = $parts[1];
            }
        }
    }

    public function getType(): string {
        return $this->type;
    }

    public function getSubtype(): string {
        return $this->subtype;
    }

    public function getSuffix(): string {
        return $this->subtype;
    }

    public function getParams(): array {
        return $this->params;
    }
    public function getParam(string $name, int $filter = FILTER_DEFAULT, $options = null) {
        if(!isset($this->params[$name]))
            return null;
        return filter_var($this->params[$name], $filter, $options);
    }

    public function getCharset(): string {
        return $this->getParam('charset', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) ?? 'utf-8';
    }
    public function getQuality(): float {
        return max(min(round($this->getParam('q', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 1, 2), 1), 0);
    }

    public function match($other): bool {
        if(is_string($other))
            return $this->matchPattern($other);
        if($other instanceof self)
            return $this->matchType($other);
        return false;
    }
    public function matchPattern(string $pattern): bool {
        try {
            $mediaType = new static($pattern);
        } catch(InvalidArgumentException $ex) {
            return false;
        }
        return $this->matchType($mediaType);
    }
    public function matchType(MediaType $other): bool {
        return ($other->getType() === '*' && $other->getSubtype() === '*')
            || (
                ($other->getType() === $this->getType())
                && ($other->getSubtype() === '*' || $other->getSubtype() === $this->getSubtype())
            );
    }

    public function __toString() {
        $string = $this->type . '/';
        $string .= $this->subtype;
        if(!empty($this->suffix))
            $string .= '+' . $this->suffix;
        if(!empty($this->params))
            foreach($this->params as $key => $value) {
                $string .= ';';
                if(!empty($key))
                    $string .= $key . '=';
                $string .= $value;
            }
        return $string;
    }
}
