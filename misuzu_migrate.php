<?php
/**
 * Migration script
 * @todo Move this into a CLI commands system.
 */

namespace Misuzu;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

require_once __DIR__ . '/misuzu.php';

$repository = new DatabaseMigrationRepository(Application::getInstance()->database->getDatabaseManager(), 'migrations');
$migrator = new Migrator($repository, $repository->getConnectionResolver(), new Filesystem);

if (!$migrator->repositoryExists()) {
    $repository->createRepository();
}

$migrator->run(__DIR__ . '/database');
//$migrator->rollback(__DIR__ . '/database');

foreach ($migrator->getNotes() as $note) {
    echo strip_tags($note) . PHP_EOL;
}
