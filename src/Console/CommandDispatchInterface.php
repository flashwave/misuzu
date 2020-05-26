<?php
namespace Misuzu\Console;

interface CommandDispatchInterface {
    public function dispatch(CommandArgs $args): void;
}
