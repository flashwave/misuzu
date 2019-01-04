<?php
function clamp($num, int $min, int $max): int
{
    return max($min, min($max, intval($num)));
}
