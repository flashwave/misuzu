<?php
namespace Misuzu;

use Misuzu\Config\ConfigManager;
use Misuzu\IO\Directory;
use Misuzu\Users\Session;
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
    ];

    /**
     * Session instance.
     * @var \Misuzu\Users\Session
     */
    private $session = null;

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
    }

    public function __destruct()
    {
        ExceptionHandler::unregister();
    }

    public function inDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function getPath(string $path): string
    {
        if (!starts_with($path, '/')) {
            $path = __DIR__ . '/../' . $path;
        }

        return Directory::fixSlashes(rtrim($path, '/'));
    }

    public function getStoragePath(string $append = ''): Directory
    {
        $path = '';

        if (starts_with($append, '/')) {
            $path = $append;
        } else {
            $path = $this->config->get('Storage', 'path', 'string', __DIR__ . '/../store');

            if (!empty($append)) {
                $path .= '/' . $append;
            }
        }

        return Directory::createOrOpen($this->getPath($path));
    }

    public function getStore(string $purpose): Directory
    {
        $override_key = 'override_' . str_replace('/', '_', $purpose);

        if ($this->config->contains('Storage', $override_key)) {
            return new Directory($this->config->get('Storage', $override_key));
        }

        return $this->getStoragePath($purpose);
    }

    public function startSession(int $user_id, string $session_key): void
    {
        $session = Session::where('session_key', $session_key)
            ->where('user_id', $user_id)
            ->first();

        if ($session !== null) {
            if ($session->hasExpired()) {
                $session->delete();
            } else {
                $this->setSession($session);
            }
        }
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): void
    {
        $this->session = $session;
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
            $section = "Database.{$name}";

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
        $twig->addFilter('country_name', 'get_country_name');
        $twig->addFilter('flip', 'array_flip');
        $twig->addFilter('create_pagination');

        // avoid using config() in templates whenever possible
        // in all honesty this shouldn't even be a thing
        $twig->addFunction('config', [$this->config, 'get']);

        $twig->addFunction('git_hash', [Application::class, 'gitCommitHash']);
        $twig->addFunction('git_branch', [Application::class, 'gitBranch']);
        $twig->addFunction('csrf_token', 'tmp_csrf_token');

        $twig->var('app', $this);

        $twig->addPath('mio', __DIR__ . '/../views/mio');
    }
}
