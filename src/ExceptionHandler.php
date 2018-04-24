<?php
namespace Misuzu;

use ErrorException;
use Throwable;

/**
 * Handles displaying and reporting exceptions after being registered.
 * @package Misuzu
 * @author Julian van de Groep <me@flash.moe>
 */
class ExceptionHandler
{
    /**
     * Url to which report objects will be POSTed.
     * @var string
     */
    private static $reportUrl = null;

    /**
     * Whether debug mode is active.
     * If true (or in CLI) a backtrace will be displayed.
     * If false a user friendly, non-exposing error page will be displayed.
     * @var bool
     */
    private static $debugMode = false;

    /**
     * Internal bool used to prevent an infinite loop when the templating engine is not available.
     * @var bool
     */
    private static $failSafe = false;

    /**
     * Registers the exception handler and make it so all errors are thrown as ErrorExceptions.
     */
    public static function register(): void
    {
        set_exception_handler([static::class, 'exception']);
        set_error_handler([static::class, 'error']);
    }

    /**
     * Same as above except unregisters
     */
    public static function unregister(): void
    {
        restore_exception_handler();
        restore_error_handler();
    }

    /**
     * Turns debug mode on or off.
     * @param bool $mode
     */
    public static function debug(bool $mode): void
    {
        static::$debugMode = $mode;
    }

    /**
     * The actual handler for rendering and reporting exceptions.
     * Checks if the exception is extends on HttpException,
     * if not an attempt will be done to report it.
     * @param Throwable $exception
     */
    public static function exception(Throwable $exception): void
    {
        $is_http = is_subclass_of($exception, HttpException::class);
        $report = !static::$debugMode && !$is_http && static::$reportUrl !== null;

        if ($report) {
            static::report($exception);
        }

        static::render($exception, $report);
    }

    /**
     * Converts regular errors to ErrorException instances.
     * @param int    $severity
     * @param string $message
     * @param string $file
     * @param int    $line
     * @throws ErrorException
     */
    public static function error(int $severity, string $message, string $file, int $line): void
    {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Shoots a POST request to the report URL.
     * @todo Implement this.
     * @param Throwable $exception
     */
    private static function report(Throwable $exception): void
    {
        // send POST request with json encoded exception to destination
    }

    /**
     * Renders exceptions.
     * In debug or cli mode a backtrace is displayed.
     * Otherwise if the error extends on HttpException the respective error code is set.
     * If the View alias is still available the script will attempt to render a path 'errors/{error code}.twig'.
     * @param Throwable $exception
     * @param bool $reported
     */
    private static function render(Throwable $exception, bool $reported): void
    {
        $is_http = false;//$exception instanceof HttpException;

        if (PHP_SAPI === 'cli' || (!$is_http && static::$debugMode)) {
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
                header('Content-Type: text/plain');
            }

            echo $exception;
            return;
        }

        $http_code = $is_http ? $exception->httpCode : 500;
        http_response_code($http_code);

        static::$failSafe = true;
        /*if (!static::$failSafe && View::available()) {
            static::$failSafe = true;
            $template = "errors.{$http_code}";
            $namespace = View::findNamespace($template);

            if ($namespace !== null) {
                echo View::render("@{$namespace}.{$template}", compact('reported'));
                return;
            }
        }*/

        if ($is_http) {
            echo "Error {$http_code}";
            return;
        }

        echo "Something broke!";

        if ($reported) {
            echo "<br>The error has been reported to the developers.";
        }
    }
}
