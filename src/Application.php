<?php
namespace Misuzu;

use Aitemu\RouteCollection;
use Misuzu\Config\ConfigManager;

class Application extends ApplicationBase
{
    private $debugMode = false;

    protected function __construct($configFile = null, bool $debug = false)
    {
        $this->debugMode = $debug;
        ExceptionHandler::register();
        ExceptionHandler::debug($this->debugMode);
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
        $twig->debug($this->debugMode);

        $twig->addFilter('json_decode');
        $twig->addFilter('byte_symbol');

        $twig->addFunction('byte_symbol');
        $twig->addFunction('session_id');
        $twig->addFunction('config', [$this->config, 'get']);
        $twig->addFunction('route', [$this->router, 'url']);
        $twig->addFunction('git_hash', [Application::class, 'gitCommitHash']);
        $twig->addFunction('git_branch', [Application::class, 'gitBranch']);

        $twig->vars(['app' => $this]);

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

    /**
     * @todo Instead of reading a connections variable from the config,
     *       the expected connections should be defined somewhere in this class.
     */
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
}
