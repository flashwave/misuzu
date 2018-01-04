<?php
namespace Misuzu;

abstract class ApplicationBase
{
    private static $instance = null;
    private $modules = [];

    public static function getInstance(): Application
    {
        if (is_null(self::$instance) || !(self::$instance instanceof Application)) {
            throw new \Exception('Invalid instance type.');
        }

        return self::$instance;
    }

    public static function start(...$params): Application
    {
        if (!is_null(self::$instance) || self::$instance instanceof Application) {
            throw new \Exception('An Application has already been set up.');
        }

        self::$instance = new static(...$params);
        return self::getInstance();
    }

    public static function gitCommitInfo(string $format): string
    {
        return trim(shell_exec(sprintf('git log --pretty="%s" -n1 HEAD', $format)));
    }

    public static function gitCommitHash(bool $long = false): string
    {
        return self::gitCommitInfo($long ? '%H' : '%h');
    }

    public static function gitBranch(): string
    {
        return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    }

    public function __get($name)
    {
        if (starts_with($name, 'has') && strlen($name) > 3 && ctype_upper($name[3])) {
            $name = lcfirst(substr($name, 3));
            return $this->hasModule($name);
        }

        if ($this->hasModule($name)) {
            return $this->modules[$name];
        }

        throw new \Exception('Invalid property.');
    }

    public function addModule(string $name, $module): void
    {
        if ($this->hasModule($name)) {
            throw new \Exception('This module has already been registered.');
        }

        $this->modules[$name] = $module;
    }

    public function hasModule(string $name): bool
    {
        return array_key_exists($name, $this->modules) && !is_null($this->modules[$name]);
    }
}
