<?php
namespace Misuzu\Console\Commands;

use Misuzu\DB;
use Misuzu\Console\CommandArgs;
use Misuzu\Console\CommandInterface;
use Misuzu\Database\DatabaseMigrationManager;

class MigrateCommand implements CommandInterface {
    public function getName(): string {
        return 'migrate';
    }
    public function getSummary(): string {
        return 'Runs database migrations.';
    }

    public function dispatch(CommandArgs $args): void {
        touch(MSZ_ROOT . '/.migrating');
        chmod(MSZ_ROOT . '/.migrating', 0777);

        echo "Creating migration manager.." . PHP_EOL;
        $migrationManager = new DatabaseMigrationManager(DB::getPDO(), MSZ_ROOT . '/database');
        $migrationManager->setLogger(function ($log) {
            echo $log . PHP_EOL;
        });

        if($args->getArg(0) === 'rollback')
            $migrationManager->rollback();
        else
            $migrationManager->migrate();

        $errors = $migrationManager->getErrors();
        $errorCount = count($errors);

        if($errorCount < 1) {
            echo 'Completed with no errors!' . PHP_EOL;
        } else {
            echo PHP_EOL . "There were {$errorCount} errors during the migrations..." . PHP_EOL;
            foreach($errors as $error)
                echo $error . PHP_EOL;
        }

        unlink(MSZ_ROOT . '/.migrating');
    }
}
