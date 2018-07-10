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
}
