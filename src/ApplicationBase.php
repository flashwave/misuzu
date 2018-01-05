<?php
namespace Misuzu;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Contains all non-specific methods, for possibly using Misuzu as a framework for other things.
 */
abstract class ApplicationBase
{
    /**
     * Things extending ApplicationBase are single instance, this property contains the active one.
     * @var ApplicationBase
     */
    private static $instance = null;

    /**
     * Holds all the loaded modules.
     * @var array
     */
    private $modules = [];

    /**
     * Gets the currently active instance of ApplicationBase
     * @return ApplicationBase
     */
    public static function getInstance(): ApplicationBase
    {
        if (is_null(self::$instance) || !(self::$instance instanceof ApplicationBase)) {
            throw new UnexpectedValueException('Invalid instance type.');
        }

        return self::$instance;
    }

    /**
     * Creates an instance of whichever class extends ApplicationBase.
     * I have no idea how to make a param for the ... thingy so ech.
     * @return ApplicationBase
     */
    public static function start(...$params): ApplicationBase
    {
        if (!is_null(self::$instance) || self::$instance instanceof ApplicationBase) {
            throw new UnexpectedValueException('An Application has already been set up.');
        }

        self::$instance = new static(...$params);
        return self::getInstance();
    }

    /**
     * Gets info from the current git commit.
     * @param string $format Follows the format of the pretty flag on the git log command
     * @return string
     */
    public static function gitCommitInfo(string $format): string
    {
        return trim(shell_exec(sprintf('git log --pretty="%s" -n1 HEAD', $format)));
    }

    /**
     * Gets the hash of the current commit.
     * @param bool $long Whether to fetch the long hash or the shorter one.
     * @return string
     */
    public static function gitCommitHash(bool $long = false): string
    {
        return self::gitCommitInfo($long ? '%H' : '%h');
    }

    /**
     * Gets the name of the current branch.
     * @return string
     */
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

        throw new InvalidArgumentException('Invalid property.');
    }

    /**
     * Adds a module to this application.
     * @param string $name
     * @param mixed $module
     */
    public function addModule(string $name, $module): void
    {
        if ($this->hasModule($name)) {
            throw new InvalidArgumentException('This module has already been registered.');
        }

        $this->modules[$name] = $module;
    }

    /**
     * Checks if a module is registered.
     * @param string $name
     * @return bool
     */
    public function hasModule(string $name): bool
    {
        return array_key_exists($name, $this->modules) && !is_null($this->modules[$name]);
    }
}
