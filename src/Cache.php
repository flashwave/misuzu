<?php
namespace Misuzu;

use Redis;
use InvalidArgumentException;
use UnexpectedValueException;

final class Cache
{
    /**
     * @var Cache
     */
    private static $instance;

    private $redis;

    public static function instance(): Cache
    {
        if (!self::hasInstance()) {
            throw new UnexpectedValueException('No instance of Cache exists yet.');
        }

        return self::$instance;
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }

    public static function hasInstance(): bool
    {
        return self::$instance instanceof static;
    }

    public function __construct(
        string $host,
        ?int $port = null,
        ?int $database = null,
        ?string $password = null,
        string $prefix = ''
    ) {
        if (self::hasInstance()) {
            throw new UnexpectedValueException('Only one instance of Cache may exist.');
        }

        self::$instance = $this;
        $this->redis = new Redis;
        $this->redis->connect($host, $port);

        if ($password !== null && !$this->redis->auth($password)) {
            throw new InvalidArgumentException('Redis auth failed.');
        }

        if ($database !== null && !$this->redis->select($database)) {
            throw new UnexpectedValueException('Redis select failed.');
        }

        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
    }

    public function __destruct()
    {
        $this->redis->close();
        self::$instance = null;
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        if (is_callable($value)) {
            $value = $value();
        }

        if ($ttl < 0) {
            return $value;
        } elseif ($ttl < 1) {
            $this->redis->set($key, $value);
        } else {
            $this->redis->setEx($key, $ttl, $value);
        }

        return $value;
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key);
    }

    public function delete($keys): int
    {
        return $this->redis->delete($keys);
    }

    public function increment(string $key, int $amount = 1): int
    {
        if ($amount <= 1) {
            return $this->redis->incr($key);
        }

        return $this->redis->incrBy($key, $amount);
    }

    public function decrement(string $key, int $amount = 1): int
    {
        if ($amount <= 1) {
            return $this->redis->decr($key);
        }

        return $this->redis->decrBy($key, $amount);
    }

    public function get(string $key, $fallback, int $ttl = 0)
    {
        if ($ttl < 0) {
            return is_callable($fallback) ? $fallback() : $fallback;
        }

        if (!$this->exists($key)) {
            return $this->set($key, $fallback, $ttl);
        }

        return $this->redis->get($key);
    }
}
