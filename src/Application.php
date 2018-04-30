<?php
namespace Misuzu;

use Misuzu\Config\ConfigManager;
use Misuzu\IO\Directory;
use Misuzu\IO\DirectoryDoesNotExistException;
use Misuzu\Users\Session;
use UnexpectedValueException;
use InvalidArgumentException;

/**
 * Handles the set up procedures.
 * @package Misuzu
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
    private $sessionInstance = null;

    /**
     * Database instance.
     * @var \Misuzu\DatabaseV1
     */
    private $databaseInstance = null;

    /**
     * Database instance.
     * @var \Misuzu\Database
     */
    private $database;

    /**
     * ConfigManager instance.
     * @var \Misuzu\Config\ConfigManager
     */
    private $configInstance = null;

    /**
     * TemplatingEngine instance.
     * @var \Misuzu\TemplateEngine
     */
    private $templatingInstance = null;

    /**
     * Constructor, called by ApplicationBase::start() which also passes the arguments through.
     * @param null|string $configFile
     * @param bool        $debug
     */
    public function __construct(?string $configFile = null, bool $debug = false)
    {
        parent::__construct();
        $this->debugMode = $debug;
        ExceptionHandler::register();
        ExceptionHandler::debug($this->debugMode);
        $this->configInstance = new ConfigManager($configFile);
    }

    /**
     * Gets instance of the config manager.
     * @return ConfigManager
     */
    public function getConfig(): ConfigManager
    {
        if (is_null($this->configInstance)) {
            throw new UnexpectedValueException('Internal ConfigManager instance is null, how did you even manage to do this?');
        }

        return $this->configInstance;
    }

    /**
     * Shuts the application down.
     */
    public function __destruct()
    {
        ExceptionHandler::unregister();
    }

    /**
     * Gets whether we're in debug mode or not.
     * @return bool
     */
    public function inDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Gets a storage path.
     * @param string $path
     * @return string
     */
    public function getPath(string $path): string
    {
        if (!starts_with($path, '/')) {
            $path = __DIR__ . '/../' . $path;
        }

        return Directory::fixSlashes(rtrim($path, '/'));
    }

    /**
     * Gets a data storage path, with config storage path prefix.
     * @param string $append
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws IO\DirectoryExistsException
     */
    public function getStoragePath(string $append = ''): Directory
    {
        if (starts_with($append, '/')) {
            $path = $append;
        } else {
            $path = $this->getConfig()->get('Storage', 'path', 'string', __DIR__ . '/../store');

            if (!empty($append)) {
                $path .= '/' . $append;
            }
        }

        return Directory::createOrOpen($this->getPath($path));
    }

    /**
     * Gets a data store, with config overrides!
     * @param string $purpose
     * @return Directory
     * @throws DirectoryDoesNotExistException
     * @throws IO\DirectoryExistsException
     */
    public function getStore(string $purpose): Directory
    {
        $override_key = 'override_' . str_replace('/', '_', $purpose);

        if ($this->configInstance->contains('Storage', $override_key)) {
            try {
                return new Directory($this->configInstance->get('Storage', $override_key));
            } catch (DirectoryDoesNotExistException $ex) {
                // fall through and just get the default path.
            }
        }

        return $this->getStoragePath($purpose);
    }

    /**
     * Starts a user session.
     * @param int    $user_id
     * @param string $session_key
     */
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

    /**
     * Gets the current session instance.
     * @return Session|null
     */
    public function getSession(): ?Session
    {
        return $this->sessionInstance;
    }

    /**
     * Registers a session.
     * @param Session|null $sessionInstance
     */
    public function setSession(?Session $sessionInstance): void
    {
        $this->sessionInstance = $sessionInstance;
    }

    /**
     * Sets up the database module.
     */
    public function startDatabase(): void
    {
        if (!is_null($this->databaseInstance)) {
            throw new UnexpectedValueException('Database module has already been started.');
        }

        $this->database = new Database($this->configInstance, self::DATABASE_CONNECTIONS[0]);
        $this->databaseInstance = new DatabaseV1($this->configInstance, self::DATABASE_CONNECTIONS[0]);
        $this->loadDatabaseConnections();
    }

    /**
     * Gets the active database instance.
     * @return DatabaseV1
     */
    public function getDatabase(): DatabaseV1
    {
        if (is_null($this->databaseInstance)) {
            throw new UnexpectedValueException('Internal database instance is null, did you run startDatabase yet?');
        }

        return $this->databaseInstance;
    }

    /**
     * Sets up the required database connections defined in the DATABASE_CONNECTIONS constant.
     */
    private function loadDatabaseConnections(): void
    {
        $config = $this->getConfig();
        $database = $this->getDatabase();

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
        if (!is_null($this->templatingInstance)) {
            throw new UnexpectedValueException('Templating module has already been started.');
        }

        $this->templatingInstance = new TemplateEngine;
        $this->templatingInstance->debug($this->debugMode);

        $this->templatingInstance->var('globals', [
            'site_name' => $this->configInstance->get('Site', 'name', 'string', 'Flashii'),
            'site_description' => $this->configInstance->get('Site', 'description'),
            'site_twitter' => $this->configInstance->get('Site', 'twitter'),
            'site_url' => $this->configInstance->get('Site', 'url'),
        ]);

        $this->templatingInstance->addFilter('json_decode');
        $this->templatingInstance->addFilter('byte_symbol');
        $this->templatingInstance->addFilter('country_name', 'get_country_name');
        $this->templatingInstance->addFilter('flip', 'array_flip');
        $this->templatingInstance->addFilter('create_pagination');
        $this->templatingInstance->addFilter('first_paragraph');
        $this->templatingInstance->addFilter('colour_get_css');
        $this->templatingInstance->addFilter('colour_get_inherit');
        $this->templatingInstance->addFilter('colour_get_red');
        $this->templatingInstance->addFilter('colour_get_green');
        $this->templatingInstance->addFilter('colour_get_blue');

        $this->templatingInstance->addFunction('git_hash', [Application::class, 'gitCommitHash']);
        $this->templatingInstance->addFunction('git_branch', [Application::class, 'gitBranch']);
        $this->templatingInstance->addFunction('csrf_token', 'tmp_csrf_token');

        $this->templatingInstance->var('app', $this);
    }

    /**
     * Gets an instance of the templating engine.
     * @return TemplateEngine
     */
    public function getTemplating(): TemplateEngine
    {
        if (is_null($this->templatingInstance)) {
            throw new UnexpectedValueException('Internal templating engine instance is null, did you run startDatabase yet?');
        }

        return $this->templatingInstance;
    }
}
