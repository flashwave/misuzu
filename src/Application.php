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

    public static function start(...$params): Application
    {
        if (!is_null(static::$instance) || static::$instance instanceof Application) {
            throw new \Exception('An Application has already been set up.');
        }

        static::$instance = new Application(...$params);
        return static::getInstance();
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

    protected function __construct($configFile = null)
    {
        ExceptionHandler::register();
        $this->addModule('config', new ConfigManager($configFile));
    }

    public function __destruct()
    {
        if ($this->hasConfig) {
            $this->config->save();
        }

        ExceptionHandler::unregister();
    }

    public function startDatabase(): void
    {
        if ($this->hasDatabase) {
            throw new \Exception('Database module has already been started.');
        }

        $config = $this->config;

        $this->addModule('database', new Database(
            $config,
            $config->get('Database', 'default', 'string', 'default')
        ));

        $this->loadConfigDatabaseConnections();
    }

    public function startTemplating(): void
    {
        if ($this->hasTemplating) {
            throw new \Exception('Templating module has already been started.');
        }

        $this->addModule('templating', $twig = new TemplateEngine);

        $twig->addFilter('json_decode');
        $twig->addFilter('byte_symbol');
        $twig->addFunction('byte_symbol');
        $twig->addFunction('session_id');
        $twig->addFunction('config', [$this->config, 'get']);
        $twig->addFunction('route', [$this->router, 'url']);
        $twig->addFunction('git_hash', [Application::class, 'gitCommitHash']);
        $twig->addFunction('git_branch', [Application::class, 'gitBranch']);
        $twig->addPath('nova', __DIR__ . '/../views/nova');
    }

    public function startRouter(array $routes = null): void
    {
        if ($this->hasRouter) {
            throw new \Exception('Router module has already been started.');
        }

        $this->addModule('router', $router = new RouteCollection);

        if ($routes !== null) {
            $router->add($routes);
        }
    }

    private function loadConfigDatabaseConnections(): void
    {
        $config = $this->config;
        $database = $this->database;

        if ($config->contains('Database', 'connections')) {
            $connections = explode(' ', $config->get('Database', 'connections'));

            foreach ($connections as $name) {
                $section = 'Database.' . $name;

                if (!$config->contains($section)) {
                    continue;
                }

                $database->addConnectionFromConfig($section, $name);
            }
        } else {
            throw new \Exception('No database connections have been configured.');
        }
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
