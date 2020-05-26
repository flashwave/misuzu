<?php
namespace Misuzu\Console;

class CommandArgs {
    private $args = [];

    public function __construct(array $args) {
        $this->args = $args;
    }

    public function getArgs(): array {
        return $this->args;
    }

    public function getCommand(): string {
        return $this->args[1] ?? '';
    }

    public function getArg(int $index): string {
        return $this->args[2 + $index] ?? '';
    }

    public function flagIndex(string $long, string $short = ''): int {
        $long = '--' . $long;
        $short = '-' . $short;
        for($i = 2; $i < count($this->args); ++$i)
            if(($long !== '--' && $this->args[$i] === $long) || ($short !== '-' && $short === $this->args[$i]))
                return $i;
        return -1;
    }

    public function hasFlag(string $long, string $short = ''): bool {
        return $this->flagIndex($long, $short) >= 0;
    }

    public function getFlag(string $long, string $short = ''): string {
        $index = $this->flagIndex($long, $short);
        if($index < 0)
            return '';
        $arg = $this->args[$index + 1] ?? '';
        if($arg[0] == '-')
            return '';
        return $arg;
    }
}
