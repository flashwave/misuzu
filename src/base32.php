<?php
define('MSZ_BASE32_CHARS', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567');

function base32_decode(string $string): string {
    $out = '';
    $length = strlen($string);
    $char = $shift = 0;

    for($i = 0; $i < $length; $i++) {
        $char <<= 5;
        $char += stripos(MSZ_BASE32_CHARS, $string[$i]);
        $shift = ($shift + 5) % 8;
        $out .= $shift < 5 ? chr(($char & (0xFF << $shift)) >> $shift) : '';
    }

    return $out;
}

function base32_encode(string $data): string {
    $bin = '';
    $encoded = '';
    $length = strlen($data);

    for($i = 0; $i < $length; $i++) {
        $bin .= sprintf('%08b', ord($data[$i]));
    }

    $bin = str_split($bin, 5);
    $last = array_pop($bin);
    $bin[] = str_pad($last, 5, '0', STR_PAD_RIGHT);

    foreach($bin as $part) {
        $encoded .= MSZ_BASE32_CHARS[bindec($part)];
    }

    return $encoded;
}
