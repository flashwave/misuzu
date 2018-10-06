<?php
define('MSZ_CACHE_REDIS_STORE', '_msz_cache_redis');
define('MSZ_CACHE_OPTIONS_STORE', '_msz_cache_options');

define('MSZ_CACHE_INIT_OK', 0);
define('MSZ_CACHE_INIT_ACTIVE', 1);
define('MSZ_CACHE_INIT_FAIL', 2);
define('MSZ_CACHE_INIT_AUTH', 3);
define('MSZ_CACHE_INIT_DATABASE', 4);

function cache_init(array $options, bool $start = false): void
{
    $GLOBALS[MSZ_CACHE_OPTIONS_STORE] = $options;

    if ($start) {
        cache_start();
    }
}

function cache_start(?array $options = null): bool
{
    if (!empty($GLOBALS[MSZ_CACHE_REDIS_STORE])) {
        return MSZ_CACHE_INIT_ACTIVE;
    }

    if ($options === null) {
        $options = $GLOBALS[MSZ_CACHE_OPTIONS_STORE] ?? [];
    }

    if (empty($options['host'])) {
        // if no host is present we just act as a void
        return MSZ_CACHE_INIT_OK;
    }

    $redis = new Redis;

    if (!$redis->connect($options['host'], $options['port'] ?? null)) {
        return MSZ_CACHE_INIT_FAIL;
    }

    if (!empty($options['password']) && !$redis->auth($options['password'])) {
        return MSZ_CACHE_INIT_AUTH;
    }

    if (!empty($options['database']) && !$redis->select($options['database'])) {
        return MSZ_CACHE_INIT_DATABASE;
    }

    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    $redis->setOption(Redis::OPT_PREFIX, $options['prefix'] ?? '');

    $GLOBALS[MSZ_CACHE_REDIS_STORE] = $redis;
    return MSZ_CACHE_INIT_OK;
}

function cache_available(): bool
{
    if (!empty($GLOBALS[MSZ_CACHE_REDIS_STORE])) {
        return true;
    }

    $startUp = cache_start();
    return $startUp === MSZ_CACHE_INIT_OK || $startUp === MSZ_CACHE_INIT_ACTIVE;
}

function cache_exists(string $key): bool
{
    return cache_available() && $GLOBALS[MSZ_CACHE_REDIS_STORE]->exists($key);
}

function cache_remove($keys): int
{
    if (!cache_available()) {
        return 0;
    }

    return $GLOBALS[MSZ_CACHE_REDIS_STORE]->delete($keys);
}

function cache_get(string $key, $fallback, int $ttl = 0)
{
    if (!cache_available() || $ttl < 0) {
        return is_callable($fallback) ? $fallback() : $fallback;
    }

    if (!cache_exists($key)) {
        return cache_set($key, $fallback, $ttl);
    }

    return $GLOBALS[MSZ_CACHE_REDIS_STORE]->get($key);
}

function cache_set(string $key, $value, int $ttl = 0)
{
    if (is_callable($value)) {
        $value = $value();
    }

    if (!cache_available() || $ttl < 0) {
        return $value;
    } elseif ($ttl < 1) {
        $GLOBALS[MSZ_CACHE_REDIS_STORE]->set($key, $value);
    } else {
        $GLOBALS[MSZ_CACHE_REDIS_STORE]->setEx($key, $ttl, $value);
    }

    return $value;
}

function cache_increment(string $key, int $amount = 1): int
{
    if (!cache_available()) {
        return abs($amount);
    }

    if ($amount <= 1) {
        return $GLOBALS[MSZ_CACHE_REDIS_STORE]->incr($key);
    }

    return $GLOBALS[MSZ_CACHE_REDIS_STORE]->incrBy($key, $amount);
}

function cache_decrement(string $key, int $amount = 1): int
{
    if (!cache_available()) {
        return abs($amount) * -1;
    }

    if ($amount <= 1) {
        return $GLOBALS[MSZ_CACHE_REDIS_STORE]->decr($key);
    }

    return $GLOBALS[MSZ_CACHE_REDIS_STORE]->decrBy($key, $amount);
}
