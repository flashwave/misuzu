<?php
/**
 * Migration script
 */

use Misuzu\Database;
use Misuzu\DatabaseMigrationManager;

require_once __DIR__ . '/misuzu.php';

define('MSZ_MIGRATABLE_DATABASES', [
    'mysql-main' => __DIR__ . '/database',
]);

function migrate_log($log): void
{
    echo $log . PHP_EOL;
}

if (PHP_SAPI !== 'cli') {
    migrate_log('This can only be run from a CLI, if you can access this from a web browser your configuration is bad.');
    exit;
}

$migrateTargets = MSZ_MIGRATABLE_DATABASES;

$doRollback = isset($argv[1]) && $argv[1] === 'rollback';
$targetDb = isset($argv[$doRollback ? 2 : 1]) ? $argv[$doRollback ? 2 : 1] : null;

if ($targetDb !== null) {
    if (array_key_exists($targetDb, $migrateTargets)) {
        $migrateTargets = [$targetDb => $migrateTargets[$targetDb]];
    } else {
        migrate_log('Invalid target database connection.');
        exit;
    }
}


foreach ($migrateTargets as $db => $path) {
    migrate_log("Creating migration manager for '{$db}'...");
    $migrationManager = new DatabaseMigrationManager(Database::connection($db), $path);
    $migrationManager->setLogger('migrate_log');

    if ($doRollback) {
        migrate_log("Rolling back last migrations for '{$db}'...");
        $migrationManager->rollback();
    } else {
        migrate_log("Running migrations for '{$db}'...");
        $migrationManager->migrate();
    }

    $errors = $migrationManager->getErrors();
    $errorCount = count($errors);

    if ($errorCount < 1) {
        migrate_log('Completed with no errors!');
    } else {
        migrate_log(PHP_EOL . "There were {$errorCount} errors during the migrations...");

        foreach ($errors as $error) {
            migrate_log($error);
        }
    }
}
