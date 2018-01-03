<?php
namespace Misuzu;

use Aitemu\RouteCollection;
use Misuzu\Config\ConfigManager;

class Application
{
    private static $instance = null;

    public static function getInstance(): Application
    {
        if (is_null(static::$instance) || !(static::$instance instanceof Application)) {
            throw new \Exception('Invalid instance type.');
        }

        return static::$instance;
    }

    public static function start(): Application
    {
        if (!is_null(static::$instance) || static::$instance instanceof Application) {
            throw new \Exception('An Application has already been set up.');
        }

        static::$instance = new Application;
        return static::getInstance();
    }

    public static function gitCommitInfo(string $format): string
    {
        return trim(shell_exec(sprintf('git log --pretty="%s" -n1 HEAD"', $format)));
    }

    public static function gitCommitHash(bool $long = false): string
    {
        return self::gitCommitInfo($long ? '%H' : '%h');
    }

    public static function gitBranch(): string
    {
        return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    }

    private $modules = [];

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

    protected function __construct($config = null)
    {
        ExceptionHandler::register();

        $this->addModule('router', new RouteCollection);
        $this->addModule('templating', new TemplateEngine);
        $this->addModule('config', new ConfigManager($config));

        $this->templating->addFilter('json_decode');
        $this->templating->addFilter('byte_symbol');
        $this->templating->addFunction('byte_symbol');
        $this->templating->addFunction('session_id');
        $this->templating->addFunction('config', [$this->config, 'get']);
        $this->templating->addFunction('route', [$this->router, 'url']);

        echo sprintf(
            'Running on commit <a href="https://github.com/flashwave/misuzu/commit/%s">%s</a> on branch <a href="https://github.com/flashwave/misuzu/tree/%3$s">%3$s</a>!',
            self::gitCommitHash(true),
            self::gitCommitHash(),
            self::gitBranch()
        );
    }

    public function __destruct()
    {
        ExceptionHandler::unregister();
    }

    public function debug(bool $mode): void
    {
        ExceptionHandler::debug($mode);

        if ($this->hasTemplating) {
            $this->templating->debug($mode);
        }
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
