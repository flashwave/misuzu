<?php
namespace Misuzu\Console;

interface CommandInterface extends CommandDispatchInterface {
    public function getName(): string;
    public function getSummary(): string;
}
