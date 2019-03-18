<?php
namespace Misuzu\Request;

class RequestVar
{
    private $value = null;
    private $valueCasted = null;
    private $type;

    protected function __construct($value, ?string $type = null)
    {
        $this->value = $value;
        $this->type = $type ?? gettype($value);
    }

    public static function get(): RequestVar
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new static($_GET ?? []);
        }

        return $instance;
    }

    public static function post(): RequestVar
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new static($_POST ?? []);
        }

        return $instance;
    }

    public function __get(string $name)
    {
        return $this->select($name);
    }

    public function __isset(string $name): bool
    {
        return $this->isset($name);
    }

    public function isset(string $name): bool
    {
        switch ($this->type) {
            case 'array':
                return isset($this->value[$name]);

            case 'object':
                return isset($this->value->{$name});

            default:
                return !is_null($this->value);
        }
    }

    public function empty(): bool
    {
        return empty($this->value);
    }

    public function raw()
    {
        return $this->value;
    }

    public function select(string $name): RequestVar
    {
        switch ($this->type) {
            case 'array':
                return new static($this->value[$name] ?? null);

            case 'object':
                return new static($this->value->{$name} ?? null);

            default:
                return new static(null);
        }
    }

    public function string(?string $default = null): ?string
    {
        return empty($this->value) ? $default : mb_scrub(preg_replace('/[\x00-\x09\x0B-\x0C\x0D-\x1F\x7F]/u', '', (string)$this->value));
    }

    public function int(?int $default = null): ?int
    {
        return empty($this->value) ? $default : (int)$this->value;
    }

    public function bool(?bool $default = null): ?bool
    {
        return empty($this->value) ? $default : (bool)$this->value;
    }

    public function float(?float $default = null): ?float
    {
        return empty($this->value) ? $default : (float)$this->value;
    }

    // avoid using when possible
    public function value(string $type = 'string', $default = null)
    {
        if (!is_null($this->valueCasted)) {
            return $this->valueCasted;
        }

        if ($this->type === 'NULL' || (($type === 'object' || $type === 'array') && $this->type !== $type)) {
            return $this->valueCasted = $default;
        }

        if ($type === 'string') {
            // Remove undesired control characters, can be circumvented by using ->raw()
            $value = $this->string($default);
        } elseif ($type !== 'string' && $this->type === 'string') {
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $value = $this->bool($default);
                    break;
                case 'integer':
                case 'int':
                    $value = $this->int($default);
                    break;
                case 'double':
                case 'float':
                    $value = $this->float($default);
                    break;
            }
        } elseif ($type !== $this->type) {
            $value = $default;
        }

        return $this->valueCasted = $this->value;
    }
}
