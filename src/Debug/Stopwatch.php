<?php
namespace Misuzu\Debug;

final class Stopwatch {
    private $startTime = 0;
    private $stopTime = 0;
    private $laps = [];

    private static $instance = null;

    public function __call(string $name, array $args) {
        if($name[0] === '_')
            return null;
        return $this->{'_' . $name}(...$args);
    }

    public static function __callStatic(string $name, array $args) {
        if($name[0] === '_')
            return null;
        if(self::$instance === null)
            self::$instance = new static;
        return self::$instance->{'_' . $name}(...$args);
    }

    private static function time() {
        return microtime(true);
    }

    public function _start(): void {
        $this->startTime = self::time();
    }

    public function _lap(string $text): void {
        $this->laps[$text] = self::time();
    }

    public function _stop(): void {
        $this->stopTime = self::time();
    }

    public function _reset(): void {
        $this->laps = [];
        $this->startTime = 0;
        $this->stopTime = 0;
    }

    public function _elapsed(): float {
        return $this->stopTime - $this->startTime;
    }

    public function _laps(): array {
        $laps = [];
        foreach($this->laps as $name => $time)
            $laps[$name] = $time - $this->startTime;
        return $laps;
    }

    public function _dump(bool $trimmed = false): void {
        header('X-Misuzu-Elapsed: ' . $this->_elapsed());
        foreach($this->_laps() as $text => $time)
            header('X-Misuzu-Lap: ' . ($trimmed ? number_format($time, 6) : $time) . ' ' . $text, false);
    }
}
