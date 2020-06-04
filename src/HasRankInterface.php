<?php
namespace Misuzu;

interface HasRankInterface {
    public function getRank(): int;
    public function HasAuthorityOver(self $other): bool;
}
