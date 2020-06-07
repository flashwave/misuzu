<?php
namespace Misuzu;

interface HasRankInterface {
    public function getRank(): int;
    public function hasAuthorityOver(self $other): bool;
}
