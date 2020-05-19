<?php
namespace Misuzu\Http\Headers;

use InvalidArgumentException;
use Misuzu\HasQualityInterface;
use Misuzu\MediaType;

class AcceptHttpHeaderChild implements HasQualityInterface {
    private $value = '';
    private $quality = null;

    public function __construct($string) {
        $parts = explode(';', $string);
        $this->value = $parts[0];

        if(isset($parts[1])) {
            $split = explode('=', $parts[1], 2);
            if($split[0] === 'q' && isset($split[1]))
                $this->quality = max(min(round(filter_var($split[1], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 1, 2), 1), 0);
        }
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getQuality(): float {
        return $this->quality ?? 1.0;
    }

    public function __toString() {
        $string = $this->value;
        if($this->quality !== null)
            $string .= ';q=' . $this->quality;
        return $string;
    }
}

class AcceptHttpHeader extends HttpHeader {
    private $accepted = null;
    private $rejected = null;

    public function isMultiline(): bool {
        return false;
    }

    private function objectType(): string {
        if($this->getName() === 'Accept')
            return MediaType::class;
        return AcceptHttpHeaderChild::class;
    }

    private function splitAccept(string $line): array {
        $split = explode(',', $line);
        $types = [];
        $objectType = $this->objectType();

        foreach($split as $type) {
            try {
                $types[] = new $objectType(trim($type));
            } catch(InvalidArgumentException $ex) {
                // Just ignore invalid types
            }
        }

        return $types;
    }

    public function set($line): void {
        $this->accepted = $this->rejected = null;
        if(is_string($line))
            $this->lines = $this->splitAccept($line);
        elseif(is_array($line)) { // please don't
            $this->lines = [];
            foreach($line as $item)
                if($item instanceof HasQualityInterface)
                    $this->lines[] = $item;
        } elseif($line instanceof HasQualityInterface)
            $this->lines = [$line];
        else
            throw new InvalidArgumentException('$line must inherit HasQualityInterface or parseable as one.');
    }
    public function append($line): void {
        $this->accepted = $this->rejected = null;
        if(is_string($line))
            $this->lines += $this->splitAccept($line);
        elseif(is_array($line)) { // please don't
            foreach($line as $item)
                if($item instanceof HasQualityInterface && !in_array($item, $this->lines))
                    $this->lines[] = $item;
        } elseif($line instanceof HasQualityInterface)
            $this->lines[] = $line;
        else
            throw new InvalidArgumentException('$line must inherit HasQualityInterface or parseable as one.');
    }

    public function getAccepted(): array {
        if($this->accepted !== null)
            return $this->accepted;
        $accepted = [];
        foreach($this->getLines() as $line)
            if($line->getQuality() > 0)
                $accepted[] = $line;
        usort($accepted, function($a, $b) {
            return $b->getQuality() <=> $a->getQuality();
        });
        return $this->accepted = $accepted;
    }

    public function getRejected(): array {
        if($this->rejected !== null)
            return $this->rejected;
        $rejected = [];
        foreach($this->getLines() as $line)
            if($line->getQuality() === 0.0)
                $rejected[] = $line;
        return $this->rejected = $rejected;
    }

    public function checkAcceptance($type): bool {
        if(is_string($type))
            try {
                $objectType = $this->objectType();
                $type = new $objectType($type);
            } catch(InvalidArgumentException $ex) {}
        if(!($type instanceof HasQualityInterface))
            throw new InvalidArgumentException('$type must inherit HasQualityInterface or parseable as one.');

        foreach($this->getRejected() as $reject)
            if($reject->matchType($reject))
                return false;

        return true;
    }
}
