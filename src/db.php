<?php
define('MSZ_DATABASE_OBJECTS_STORE', '_msz_database_connects');
define('MSZ_DATABASE_DEFAULT_NAME_STORE', '_msz_database_default');
define('MSZ_DATABASE_OPTIONS_STORE', '_msz_database_options');

define('MSZ_DATABASE_SUPPORTED', [
    'mysql',
    'sqlite',
]);
define('MSZ_DATABASE_MYSQL_DEFAULTS', [
    'host' => '127.0.0.1',
    'port' => 3306,
]);

function db_setup(array $databases, ?string $default = null): void
{
    $GLOBALS[MSZ_DATABASE_OPTIONS_STORE] = $databases;
    $GLOBALS[MSZ_DATABASE_DEFAULT_NAME_STORE] = $default ?? key($databases);
}

function db_connection(?string $name = null): ?PDO
{
    $name = $name ?? $GLOBALS[MSZ_DATABASE_DEFAULT_NAME_STORE] ?? '';

    if (empty($GLOBALS[MSZ_DATABASE_OBJECTS_STORE][$name])
        && !empty($GLOBALS[MSZ_DATABASE_OPTIONS_STORE][$name])) {
        return db_connect($name, $GLOBALS[MSZ_DATABASE_OPTIONS_STORE][$name]);
    }

    return $GLOBALS[MSZ_DATABASE_OBJECTS_STORE][$name] ?? null;
}

function db_prepare(string $statement, ?string $connection = null, $options = []): PDOStatement
{
    static $stmts = [];
    $encodedOptions = serialize($options);

    if (!empty($stmts[$connection][$statement][$encodedOptions])) {
        return $stmts[$connection][$statement][$encodedOptions];
    }

    return $stmts[$connection][$statement][$encodedOptions] = db_prepare_direct($statement, $connection, $options);
}

function db_prepare_direct(string $statement, ?string $connection = null, $options = []): PDOStatement
{
    return db_connection($connection)->prepare($statement, $options);
}

function db_query(string $statement, ?string $connection = null): PDOStatement
{
    return db_connection($connection)->query($statement);
}

function db_exec(string $statement, ?string $connection = null)
{
    return db_connection($connection)->exec($statement);
}

function db_last_insert_id(?string $name = null, ?string $connection = null): string
{
    return db_connection($connection)->lastInsertId($name);
}

function db_query_count(?string $connection = null): int
{
    return (int)db_query('SHOW SESSION STATUS LIKE "Questions"', $connection)->fetchColumn(1);
}

function db_fetch(PDOStatement $stmt, $default = [])
{
    $out = $stmt->execute() ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $out ? $out : $default;
}

function db_fetch_all(PDOStatement $stmt, $default = [])
{
    $out = $stmt->execute() ? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
    return $out ? $out : $default;
}

// starting at 2
define('MSZ_DATABASE_CONNECT_UNSUPPORTED', 2);
define('MSZ_DATABASE_CONNECT_NO_DATABASE', 3);

function db_connect(string $name, ?array $options = null)
{
    if (!empty($GLOBALS[MSZ_DATABASE_OBJECTS_STORE][$name])) {
        return $GLOBALS[MSZ_DATABASE_OBJECTS_STORE][$name];
    }

    if ($options === null) {
        $options = $GLOBALS[MSZ_DATABASE_OPTIONS_STORE][$name] ?? [];
    }

    if (!in_array($options['driver'], MSZ_DATABASE_SUPPORTED)) {
        return MSZ_DATABASE_CONNECT_UNSUPPORTED;
    }

    $dsn = "{$options['driver']}:";
    $pdoOptions = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    switch ($options['driver']) {
        case 'sqlite':
            if ($options['memory']) {
                $dsn .= ':memory:';
            } else {
                $databasePath = realpath($options['database'] ?? MSZ_ROOT . '/store/misuzu.db');

                if ($databasePath === false) {
                    return MSZ_DATABASE_CONNECT_NO_DATABASE;
                }
            }
            break;

        case 'mysql':
            $options = array_merge(MSZ_DATABASE_MYSQL_DEFAULTS, $options);

            $dsn .= empty($options['unix_socket'])
                ? sprintf('host=%s;port=%d;', $options['host'], $options['port'])
                : sprintf('unix_socket=%s;', $options['unix_socket']);

            $dsn .= sprintf(
                'charset=%s;dbname=%s;',
                $options['charset'] ?? 'utf8mb4',
                $options['database'] ?? 'misuzu'
            );

            $pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "
                SET SESSION
                    sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                    time_zone = '+00:00';
            ";
            break;
    }

    $connection = new PDO(
        $dsn,
        $options['username'] ?? null,
        $options['password'] ?? null,
        $pdoOptions
    );

    return $GLOBALS[MSZ_DATABASE_OBJECTS_STORE][$name] = $connection;
}
