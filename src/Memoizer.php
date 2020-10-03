<?php
namespace Misuzu;

use InvalidArgumentException;

class Memoizer {
    private $collection = [];

    public function find($find, callable $create) {
        if(is_int($find) || is_string($find)) {
            if(!isset($this->collection[$find]))
                $this->collection[$find] = $create();
            return $this->collection[$find];
        }

        if(is_callable($find)) {
            $item = array_find($this->collection, $find) ?? $create();
            if($item !== null)
                $this->insert($item);
            return $item;
        }

        throw new InvalidArgumentException('Wasn\'t able to figure out your $find argument.');
    }

    public function insert($item): void {
        if($item === null)
            throw new InvalidArgumentException('null');
        if(method_exists($item, 'getId'))
            $this->collection[$item->getId()] = $item;
        else
            $this->collection[] = $item;
    }
}
