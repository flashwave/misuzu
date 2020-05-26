<?php
namespace Misuzu\Console;

class CommandManagerException extends ConsoleException {}
class CommandNotFoundException extends CommandManagerException {}

class CommandCollection implements CommandDispatchInterface {
    private $commands = [];

    public function addCommands(CommandInterface ...$commands): void {
        foreach($commands as $command)
            try {
                $this->matchCommand($command->getName());
            } catch(CommandNotFoundException $ex) {
                $this->commands[] = $command;
            }
    }

    public function matchCommand(string $name): CommandInterface {
        foreach($this->commands as $command)
            if($command->getName() === $name)
                return $command;
        throw new CommandNotFoundException;
    }

    public function dispatch(CommandArgs $args): void {
        try {
            $this->matchCommand($args->getCommand())->dispatch($args);
        } catch(CommandNotFoundException $ex) {
            echo 'Command not found.' . PHP_EOL;
        }
    }
}
