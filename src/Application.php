<?php
namespace Misuzu;

use Aitemu\RouteCollection;
use Misuzu\Config\ConfigManager;
use UnexpectedValueException;
use InvalidArgumentException;

/**
 * Handles the set up procedures.
 */
class Application extends ApplicationBase
{
    /**
     * Whether the application is in debug mode, this should only be set in the constructor and never altered.
     * @var bool
     */
    private $debugMode = false;

    /**
     * Array of database connection names, first in the list is assumed to be the default.
     */
    private const DATABASE_CONNECTIONS = [
        'mysql-main',
        //'mysql-ayase',
    ];

    /**
     * Constructor, called by ApplicationBase::start() which also passes the arguments through.
     * @param ?string $configFile
     * @param bool $debug
     */
    protected function __construct(?string $configFile = null, bool $debug = false)
    {
        $this->debugMode = $debug;
        ExceptionHandler::register();
        ExceptionHandler::debug($this->debugMode);
        $this->addModule('config', new ConfigManager($configFile));

        // temporary session system
        session_start();
    }

    public function __destruct()
    {
        ExceptionHandler::unregister();
    }

    /**
     * Sets up the database module.
     */
    public function startDatabase(): void
    {
        if ($this->hasDatabase) {
            throw new UnexpectedValueException('Database module has already been started.');
        }

        $this->addModule('database', new Database($this->config, self::DATABASE_CONNECTIONS[0]));
        $this->loadDatabaseConnections();
    }

    /**
     * Sets up the required database connections defined in the DATABASE_CONNECTIONS constant.
     */
    private function loadDatabaseConnections(): void
    {
        $config = $this->config;
        $database = $this->database;

        foreach (self::DATABASE_CONNECTIONS as $name) {
            $section = 'Database.' . $name;

            if (!$config->contains($section)) {
                throw new InvalidArgumentException("Database {$name} is not configured.");
            }

            $database->addConnectionFromConfig($section, $name);
        }
    }

    /**
     * Sets up the templating engine module.
     */
    public function startTemplating(): void
    {
        if ($this->hasTemplating) {
            throw new UnexpectedValueException('Templating module has already been started.');
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

        $twig->vars(['app' => $this, 'tsession' => $_SESSION]);

        $twig->addPath('nova', __DIR__ . '/../views/nova');
    }

    /**
     * Sets up the router module.
     */
    public function startRouter(array $routes = null): void
    {
        if ($this->hasRouter) {
            throw new UnexpectedValueException('Router module has already been started.');
        }

        $this->addModule('router', $router = new RouteCollection);

        if ($routes !== null) {
            $router->add($routes);
        }
    }
}
