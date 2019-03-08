<?php
 namespace Misuzu\Request;

class RequestVar
{
    private $value;
    private $valueCasted = null;
    private $type;

    protected function __construct($value, ?string $type = null)
    {
        $this->value = $value;
        $this->type = $type ?? gettype($value);
    }

    public static function get(): RequestVar
    {
        return new static($_GET ?? []);
    }

    public static function post(): RequestVar
    {
        return new static($_POST ?? []);
    }

    public static function request(): RequestVar
    {
        return new static($_REQUEST);
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

    public function select(string $name): RequestVar
    {
        switch ($this->type) {
            case 'array':
                return new static($this->value[$name] ?? []);

            case 'object':
                return new static($this->value->{$name} ?? new \stdClass);

            default:
                return new static(null);
        }
    }

    public function value(string $type = 'string', $default = null)
    {
        if (!is_null($this->valueCasted)) {
            $this->valueCasted;
        }

        if ($this->type === 'NULL' || (($type === 'object' || $type === 'array') && $this->type !== $type)) {
            return $default;
        }

        if ($type !== 'string' && $this->type === 'string') {
            switch ($type) {
                case 'boolean':
                case 'bool':
                    return (bool)$this->value;
                case 'integer':
                case 'int':
                    return (int)$this->value;
                case 'double':
                case 'float':
                    return (float)$this->value;
            }
        } elseif ($type !== $this->type) {
            return $default;
        }

        return $this->valueCasted = $this->value;
    }
}
