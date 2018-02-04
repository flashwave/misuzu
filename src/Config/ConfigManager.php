<?php
namespace Misuzu\Config;

use Misuzu\IO\File;
use Misuzu\IO\FileStream;

/**
 * Handles parsing, reading and setting configuration files.
 * @package Aitemu\Config
 * @author Julian van de Groep <me@flash.moe>
 */
class ConfigManager
{
    /**
     * Holds the key collection pairs for the sections.
     * @var array
     */
    private $collection = [];

    private $filename = null;

    /**
     * Creates a file object with the given path and reloads the context.
     * @param string $filename
     */
    public function __construct(?string $filename = null)
    {
        if (empty($filename)) {
            return;
        }

        $this->filename = $filename;

        if (File::exists($this->filename)) {
            $this->load();
        }
    }

    /**
     * Checks if a section or key exists in the config.
     * @param string $section
     * @param string $key
     * @return bool
     */
    public function contains(string $section, ?string $key = null): bool
    {
        if ($key !== null) {
            return $this->contains($section) && array_key_exists($key, $this->collection[$section]);
        }

        return array_key_exists($section, $this->collection);
    }

    /**
     * Removes a section or key and saves.
     * @param string $section
     * @param string $key
     */
    public function remove(string $section, ?string $key = null): void
    {
        if ($key !== null && $this->contains($section, $key)) {
            if (count($this->collection[$section]) < 2) {
                $this->remove($section);
                return;
            }

            unset($this->collection[$section][$key]);
        } elseif ($this->contains($section)) {
            unset($this->collection[$section]);
        }
    }

    /**
     * Gets a value from a section in the config.
     * @param string $section
     * @param string $key
     * @param string $type
     * @param string $fallback
     * @return mixed
     */
    public function get(string $section, string $key, string $type = 'string', ?string $fallback = null)
    {
        $value = null;

        if (!$this->contains($section, $key)) {
            $this->set($section, $key, $fallback);
            return $fallback;
        }

        $raw = $this->collection[$section][$key];

        switch (strtolower($type)) {
            case "bool":
            case "boolean":
                $value = strlen($raw) > 0 && ($raw[0] === '1' || strtolower($raw) === "true");
                break;

            case "int":
            case "integer":
                $value = intval($raw);
                break;

            case "float":
            case "double":
                $value = floatval($raw);
                break;

            default:
                $value = $raw;
                break;
        }

        return $value;
    }

    /**
     * Sets a configuration value and immediately saves it.
     * @param string $section
     * @param string $key
     * @param mixed $value
     */
    public function set(string $section, string $key, $value): void
    {
        $type = gettype($value);
        $store = null;

        switch (strtolower($type)) {
            case 'boolean':
                $store = $value ? '1' : '0';
                break;

            default:
                $store = (string)$value;
                break;
        }

        if (!$this->contains($section)) {
            $this->collection[$section] = [];
        }

        $this->collection[$section][$key] = $store;
    }

    /**
     * Writes the serialised config to file.
     */
    public function save(): void
    {
        if (!empty($this->filename)) {
            static::write($this->filename, $this->collection);
        }
    }

    /**
     * Calls for a parse of the contents of the config file.
     */
    public function load(): void
    {
        if (!empty($this->filename)) {
            $this->collection = static::read($this->filename);
        }
    }

    /**
     * Serialises the $this->collection array to the human readable config format.
     * @return string
     */
    public static function write(string $filename, array $collection): void
    {
        $file = new FileStream($filename, FileStream::MODE_TRUNCATE, true);
        $file->write(sprintf('; Saved on %s%s', date('Y-m-d H:i:s e'), PHP_EOL));

        foreach ($collection as $name => $entries) {
            if (count($entries) < 1) {
                continue;
            }

            $file->write(sprintf('%1$s[%2$s]%1$s', PHP_EOL, $name));

            foreach ($entries as $key => $value) {
                $file->write(sprintf('%s = %s%s', $key, $value, PHP_EOL));
            }
        }

        $file->flush();
        $file->close();
    }

    /**
     * Parses the config file.
     * @param string $config
     */
    private static function read(string $filename): array
    {
        $collection = [];
        $section = null;
        $key = null;
        $value = null;

        $file = new FileStream($filename, FileStream::MODE_READ);
        $lines = explode("\n", $file->read($file->length));
        $file->close();

        foreach ($lines as $line) {
            $line = trim($line, "\r\n");
            $length = strlen($line);

            if ($length < 1
                || starts_with($line, '#')
                || starts_with($line, ';')
                || starts_with($line, '//')
                ) {
                continue;
            }

            if (starts_with($line, '[') && ends_with($line, ']')) {
                $section = rtrim(ltrim($line, '['), ']');

                if (!isset($collection[$section])) {
                    $collection[$section] = [];
                }
                continue;
            }

            if (strpos($line, '=') !== false) {
                $split = explode('=', $line, 2);

                if (count($split) < 2) {
                    continue;
                }

                $key = trim($split[0]);
                $value = trim($split[1]);

                if (strlen($key) > 0 && strlen($value) > 0) {
                    $collection[$section][$key] = $value;
                }
            }
        }

        return $collection;
    }
}
