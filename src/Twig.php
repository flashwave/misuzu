<?php
namespace Misuzu;

use Twig_Environment;
use Twig_LoaderInterface;
use UnexpectedValueException;

final class Twig extends Twig_Environment {
    protected static $instance = null;

    public static function instance(): Twig_Environment {
        return self::$instance;
    }

    public function __construct(Twig_LoaderInterface $loader, array $options = []) {
        if(self::$instance !== null) {
            throw new UnexpectedValueException('Instance of Twig already present, use the static instance() function.');
        }

        parent::__construct($loader, $options);
        self::$instance = $this;
    }
}
