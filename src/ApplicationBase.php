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
     * ApplicationBase constructor.
     */
    public function __construct()
    {
        if (!is_null(self::$instance) || self::$instance instanceof ApplicationBase) {
            throw new UnexpectedValueException('An Application has already been set up.');
        }

        self::$instance = $this;
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
}
