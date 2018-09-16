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
     * HMAC key that will be used to sign the request.
     * @var string
     */
    private static $reportSign = null;

    /**
     * Whether debug mode is active.
     * If true (or in CLI) a backtrace will be displayed.
     * If false a user friendly, non-exposing error page will be displayed.
     * @var bool
     */
    private static $debugMode = false;

    /**
     * Registers the exception handler and make it so all errors are thrown as ErrorExceptions.
     * @param bool $debugMode
     * @param string|null $reportUrl
     * @param string|null $reportSign
     */
    public static function register(bool $debugMode, ?string $reportUrl = null, ?string $reportSign = null): void
    {
        self::$debugMode = $debugMode;
        self::$reportUrl = $reportUrl;
        self::$reportSign = $reportSign;
        set_exception_handler([self::class, 'exception']);
        set_error_handler([self::class, 'error']);
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
     * The actual handler for rendering and reporting exceptions.
     * Checks if the exception is extends on HttpException,
     * if not an attempt will be done to report it.
     * @param Throwable $exception
     */
    public static function exception(Throwable $exception): void
    {
        $report = !self::$debugMode && self::$reportUrl !== null;

        if ($report) {
            self::report($exception);
        }

        self::render($exception, $report);
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
     * @param Throwable $throwable
     */
    private static function report(Throwable $throwable): bool
    {
        if (!mb_strlen(self::$reportUrl)) {
            return false;
        }

        if (!($curl = curl_init(self::$reportUrl))) {
            return false;
        }

        $json = json_encode([
            'git' => [
                'branch' => git_branch(),
                'hash' => git_commit_hash(true),
            ],
            'exception' => [
                'type' => get_class($throwable),
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'file' => str_replace(dirname(__DIR__, 1), '', $throwable->getFile()),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ],
        ]);

        $headers = [
            'Content-Type: application/json;charset=utf-8',
        ];

        if (mb_strlen(self::$reportSign)) {
            $headers[] = 'X-Misuzu-Signature: sha256=' . hash_hmac('sha256', $json, self::$reportSign);
        }

        $setOpts = curl_setopt_array($curl, [
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if (!$setOpts) {
            return false;
        }

        return curl_exec($curl) !== false;
    }

    /**
     * Renders exceptions.
     * In debug or cli mode a backtrace is displayed.
     * @param Throwable $exception
     * @param bool $reported
     */
    private static function render(Throwable $exception, bool $reported): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            ob_clean();
            http_response_code(500);
            header('Content-Type: text/plain');
        }

        if (PHP_SAPI === 'cli' || self::$debugMode) {
            echo $exception;
        } else {
            echo 'Something broke!';

            if ($reported) {
                echo PHP_EOL . 'Information about this error has been sent to the devs.';
            }
        }
    }
}
