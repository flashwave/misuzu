<?php
namespace Misuzu\Parsers;

abstract class Parser
{
    private static $instances = [];

    public function __construct()
    {
        self::$instances[static::class] = $this;
    }

    public static function getInstance(): ?Parser
    {
        if (!array_key_exists(static::class, self::$instances)) {
            return null;
        }

        return self::$instances[static::class];
    }

    public static function getOrCreateInstance(...$args): Parser
    {
        $instance = static::getInstance();

        if ($instance === null) {
            $instance = new static(...$args);
        }

        return $instance;
    }

    abstract public function parseText(string $text): string;
}
