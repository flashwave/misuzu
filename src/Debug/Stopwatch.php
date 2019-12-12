<?php
namespace Misuzu\Debug;

class Stopwatch {
    private $startTime = 0;
    private $stopTime = 0;
    private $laps = [];

    private static $instance = null;

    public static function __callStatic(string $name, array $args) {
        if(self::$instance === null)
            self::$instance = new static;
        return self::$instance->{substr($name, 1)}(...$args);
    }

    public function __construct() {}

    private static function time() {
        return microtime(true);
    }

    public function start(): void {
        $this->startTime = self::time();
    }

    public function lap(string $text): void {
        $this->laps[$text] = self::time();
    }

    public function stop(): void {
        $this->stopTime = self::time();
    }

    public function reset(): void {
        $this->laps = [];
        $this->startTime = 0;
        $this->stopTime = 0;
    }

    public function elapsed(): float {
        return $this->stopTime - $this->startTime;
    }

    public function laps(): array {
        $laps = [];

        foreach($this->laps as $name => $time) {
            $laps[$name] = $time - $this->startTime;
        }

        return $laps;
    }
}
