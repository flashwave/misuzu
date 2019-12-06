<?php
namespace Misuzu;

final class Base32 {
    public const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function decode(string $str): string {
        $out = '';
        $length = strlen($str);
        $char = $shift = 0;

        for($i = 0; $i < $length; $i++) {
            $char <<= 5;
            $char += stripos(self::CHARS, $str[$i]);
            $shift = ($shift + 5) % 8;
            $out .= $shift < 5 ? chr(($char & (0xFF << $shift)) >> $shift) : '';
        }

        return $out;
    }

    public static function encode(string $data): string {
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
            $encoded .= self::CHARS[bindec($part)];
        }

        return $encoded;
    }
}
