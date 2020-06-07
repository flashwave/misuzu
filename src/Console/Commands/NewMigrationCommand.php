<?php
namespace Misuzu\Console\Commands;

use Misuzu\Console\CommandArgs;
use Misuzu\Console\CommandInterface;

class NewMigrationCommand implements CommandInterface {
    private const TEMPLATE = <<<MIG
<?php
namespace Misuzu\DatabaseMigrations\\%s;

use PDO;

function migrate_up(PDO \$conn): void {
    \$conn->exec("
        CREATE TABLE ...
    ");
}

function migrate_down(PDO \$conn): void {
    \$conn->exec("
        DROP TABLE ...
    ");
}

MIG;

    public function getName(): string {
        return 'new-mig';
    }
    public function getSummary(): string {
        return 'Creates a new database migration.';
    }

    public function dispatch(CommandArgs $args): void {
        $name = str_replace(' ', '_', implode(' ', $args->getArgs()));

        if(empty($name)) {
            echo 'Specify a migration name.' . PHP_EOL;
            return;
        }

        if(!preg_match('#^([a-z_]+)$#', $name)) {
            echo 'Migration name may only contain alpha and _ characters.' . PHP_EOL;
            return;
        }

        $fileName = date('Y_m_d_His_') . trim($name, '_') . '.php';
        $filePath = MSZ_ROOT . '/database/' . $fileName;
        $namespace = str_replace('_', '', ucwords($name, '_'));

        file_put_contents($filePath, sprintf(self::TEMPLATE, $namespace));

        echo "Template for '{$namespace}' has been created." . PHP_EOL;
    }
}
