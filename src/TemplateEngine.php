<?php
namespace Misuzu;

use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

/**
 * Wrapper for Twig.
 * @package Misuzu
 * @author Julian van de Groep <me@flash.moe>
 */
class TemplateEngine
{
    /**
     * Utility |filters().
     */
    private const UTILITY_FILTERS = [
        'json_decode',
        //'byte_symbol',
    ];

    /**
     * Utility functions().
     */
    private const UTILITY_FUNCTIONS = [
        //'route',
        //'config',
        //'session_id',
    ];

    /**
     * Template file extension.
     */
    private const FILE_EXTENSION = '.twig';

    /**
     * Container for twig.
     * @var Twig_Environment
     */
    private $twig;

    private $loader;

    /**
     * Render arguments.
     * @var array
     */
    private $vars = [];

    /**
     * Creates the twig environment and registers the utility filters and functions.
     */
    public function __construct()
    {
        $this->loader = new Twig_Loader_Filesystem;
        $this->twig = new Twig_Environment($this->loader, [
            'cache' => false,
            'strict_variables' => true,
            'auto_reload' => false,
            'debug' => false,
        ]);

        foreach (static::UTILITY_FILTERS as $filter) {
            $this->addFilter($filter, $filter);
        }

        foreach (static::UTILITY_FUNCTIONS as $function) {
            $this->addFunction($function, $function);
        }
    }

    /**
     * Toggles debug mode on or off.
     * @param bool $mode
     */
    public function debug(bool $mode): void
    {
        if ($this->twig->isDebug() === $mode) {
            return;
        }

        if ($mode) {
            $this->twig->enableDebug();
            return;
        }

        $this->twig->disableDebug();
    }

    /**
     * Toggles cache auto reloading on or off.
     * @param bool $mode
     */
    public function autoReload(bool $mode): void
    {
        if ($this->twig->isAutoReload() === $mode) {
            return;
        }

        if ($mode) {
            $this->twig->enableAutoReload();
            return;
        }

        $this->twig->disableAutoReload();
    }

    /**
     * Sets the cache path and alternatively turns it off.
     * @param string $path
     */
    public function cache(bool $path): void
    {
        $this->twig->setCache($path);
    }

    /**
     * Adds a template path, first one is regarded as the master.
     * @param string $name
     * @param string $path
     */
    public function addPath(string $name, string $path): void
    {
        if (count($this->loader->getPaths()) < 1) {
            $this->loader->addPath($path);
        }

        $this->loader->addPath($path, $name);
    }

    /**
     * Sets render vars.
     * @param array $vars
     */
    public function vars(array $vars): void
    {
        $this->vars = array_merge($this->vars, $vars);
    }

    /**
     * Converts . to / and appends the file extension.
     * @param string $path
     * @return string
     */
    private function fixPath(string $path): string
    {
        // if the .twig extension if already present just assume that the path is already correct
        if (substr($path, 0 - strlen(static::FILE_EXTENSION)) === static::FILE_EXTENSION) {
            return $path;
        }

        return str_replace('.', '/', $path) . static::FILE_EXTENSION;
    }

    /**
     * Renders a template file.
     * @param string $path
     * @param array $vars
     * @return string
     */
    public function render(string $path, array $vars = null): string
    {
        $path = static::fixPath($path);

        if ($vars !== null) {
            $this->vars($vars);
        }

        if (!$this->exists($path, Twig_Loader_Filesystem::MAIN_NAMESPACE)) {
            $namespace = $this->findNamespace($path);

            if ($namespace !== null) {
                $path = '@' . $this->findNamespace($path) . '/' . $path;
            }
        }

        return $this->twig->render($path, $this->vars);
    }

    /**
     * Adds a function.
     * @param string $name
     * @param Callable $callable
     */
    public function addFunction(string $name, Callable $callable = null): void
    {
        $this->twig->addFunction(new Twig_SimpleFunction($name, $callable === null ? $name : $callable));
    }

    /**
     * Adds a filter.
     * @param string $name
     * @param Callable $callable
     */
    public function addFilter(string $name, Callable $callable = null): void
    {
        $this->twig->addFilter(new Twig_SimpleFilter($name, $callable === null ? $name : $callable));
    }

    /**
     * Finds in which namespace a template exists.
     * @param string $path
     * @return string
     */
    public function findNamespace(string $path): string
    {
        foreach ($this->loader->getNamespaces() as $namespace) {
            if ($this->exists($path, $namespace)) {
                return $namespace;
            }
        }

        return null;
    }

    /**
     * Checks if a template exists.
     * @param string $path
     * @param string $namespace
     * @return bool
     */
    public function exists(string $path, string $namespace): bool
    {
        return $this->loader->exists('@' . $namespace . '/' . static::fixPath($path));
    }
}
