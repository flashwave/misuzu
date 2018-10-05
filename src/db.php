<?php
define('MSZ_DATABASE_NAMES', [
    'mysql-main',
]);

function db_setup(string $name, array $options): void
{
    // todo: :puke:
    new Misuzu\Database([$name => $options], $name);
}
