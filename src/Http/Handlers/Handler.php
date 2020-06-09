<?php
namespace Misuzu\Http\Handlers;

abstract class Handler {
    public function __construct() {
        \Misuzu\mszLockdown();
    }
}
