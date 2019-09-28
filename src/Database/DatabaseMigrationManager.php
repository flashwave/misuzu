<?php
namespace Misuzu\Database;

use Exception;
use PDO;
use PDOException;

final class DatabaseMigrationManager {
    private $targetConnection;
    private $migrationStorage;

    private const MIGRATION_NAMESPACE = '\\Misuzu\\DatabaseMigrations\\%s\\%s';

    private $errors = [];

    private $logFunction;

    public function __construct(PDO $conn, string $path) {
        $this->targetConnection = $conn;
        $this->migrationStorage = realpath($path);
    }

    private function addError(Exception $exception): void {
        $this->errors[] = $exception;
        $this->writeLog($exception->getMessage());
    }

    public function setLogger(callable $logger): void {
        $this->logFunction = $logger;
    }

    private function writeLog(string $log): void {
        if(!is_callable($this->logFunction)) {
            return;
        }

        call_user_func($this->logFunction, $log);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    private function getMigrationScripts(): array {
        if(!file_exists($this->migrationStorage) || !is_dir($this->migrationStorage)) {
            $this->addError(new Exception('Migrations script directory does not exist.'));
            return [];
        }

        $files = glob(rtrim($this->migrationStorage, '/\\') . '/*.php');
        return $files;
    }

    private function createMigrationRepository(): bool {
        try {
            $this->targetConnection->exec('
                CREATE TABLE IF NOT EXISTS `msz_migrations` (
                    `migration_id`      INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
                    `migration_name`    VARCHAR(255)        NOT NULL,
                    `migration_batch`   INT(11) UNSIGNED    NOT NULL,
                    PRIMARY KEY (`migration_id`),
                    UNIQUE INDEX (`migration_id`)
                )
            ');
        } catch(PDOException $ex) {
            $this->addError($ex);
            return false;
        }

        return true;
    }

    public function migrate(): bool {
        $this->writeLog('Running migrations...');

        if(!$this->createMigrationRepository()) {
            return false;
        }

        $migrationScripts = $this->getMigrationScripts();

        if(count($migrationScripts) < 1) {
            if(count($this->errors) > 0) {
                return false;
            }

            $this->writeLog('Nothing to migrate!');
            return true;
        }

        try {
            $this->writeLog('Fetching completed migration...');
            $fetchStatus = $this->targetConnection->prepare("
                SELECT *, CONCAT(:basepath, '/', `migration_name`, '.php') as `migration_path`
                FROM `msz_migrations`
            ");
            $fetchStatus->bindValue('basepath', $this->migrationStorage);
            $migrationStatus = $fetchStatus->execute() ? $fetchStatus->fetchAll() : [];
        } catch(PDOException $ex) {
            $this->addError($ex);
            return false;
        }

        if(count($migrationStatus) < 1 && count($this->errors) > 0) {
            return false;
        }

        $remainingMigrations = array_diff($migrationScripts, array_column($migrationStatus, 'migration_path'));

        if(count($remainingMigrations) < 1) {
            $this->writeLog('Nothing to migrate!');
            return true;
        }

        $batchNumber = $this->targetConnection->query('
            SELECT COALESCE(MAX(`migration_batch`), 0) + 1
            FROM `msz_migrations`
        ')->fetchColumn();

        $recordMigration = $this->targetConnection->prepare('
            INSERT INTO `msz_migrations`
                (`migration_name`, `migration_batch`)
            VALUES
                (:name, :batch)
        ');
        $recordMigration->bindValue('batch', $batchNumber);

        foreach($remainingMigrations as $migration) {
            $filename = pathinfo($migration, PATHINFO_FILENAME);
            $filenameSplit = explode('_', $filename);
            $recordMigration->bindValue('name', $filename);
            $migrationName = '';

            if(count($filenameSplit) < 5) {
                $this->addError(new Exception("Invalid migration name: '{$filename}'"));
                return false;
            }

            for($i = 4; $i < count($filenameSplit); $i++) {
                $migrationName .= ucfirst(mb_strtolower($filenameSplit[$i]));
            }

            include_once $migration;

            $this->writeLog("Running migration '{$filename}'...");
            $migrationFunction = sprintf(self::MIGRATION_NAMESPACE, $migrationName, 'migrate_up');
            $migrationFunction($this->targetConnection);
            $recordMigration->execute();
        }

        $this->writeLog('Successfully completed all migrations!');

        return true;
    }

    public function rollback(): bool
    {
        $this->writeLog('Rolling back last migration batch...');

        if(!$this->createMigrationRepository()) {
            return false;
        }

        try {
            $fetchStatus = $this->targetConnection->prepare("
                SELECT *, CONCAT(:basepath, '/', `migration_name`, '.php') as `migration_path`
                FROM `msz_migrations`
                WHERE `migration_batch` = (
                    SELECT MAX(`migration_batch`)
                    FROM `msz_migrations`
                )
            ");
            $fetchStatus->bindValue('basepath', $this->migrationStorage);
            $migrations = $fetchStatus->execute() ? $fetchStatus->fetchAll() : [];
        } catch(PDOException $ex) {
            $this->addError($ex);
            return false;
        }

        if(count($migrations) < 1) {
            if(count($this->errors) > 0) {
                return false;
            }

            $this->writeLog('Nothing to roll back!');
            return true;
        }

        $migrationScripts = $this->getMigrationScripts();

        if(count($migrationScripts) < count($migrations)) {
            $this->addError(new Exception('There are missing migration scripts!'));
            return false;
        }

        $removeRecord = $this->targetConnection->prepare('
            DELETE FROM `msz_migrations`
            WHERE `migration_id` = :id
        ');

        foreach($migrations as $migration) {
            if(!file_exists($migration['migration_path'])) {
                $this->addError(new Exception("Migration '{$migration['migration_name']}' does not exist."));
                return false;
            }

            $nameSplit = explode('_', $migration['migration_name']);
            $migrationName = '';

            for($i = 4; $i < count($nameSplit); $i++) {
                $migrationName .= ucfirst(mb_strtolower($nameSplit[$i]));
            }

            include_once $migration['migration_path'];

            $this->writeLog("Rolling '{$migration['migration_name']}' back...");
            $migrationFunction = sprintf(self::MIGRATION_NAMESPACE, $migrationName, 'migrate_down');
            $migrationFunction($this->targetConnection);

            $removeRecord->bindValue('id', $migration['migration_id']);
            $removeRecord->execute();
        }

        $this->writeLog('Successfully completed all rollbacks');

        return true;
    }
}
