<?php
namespace Misuzu;

final class Zalgo {
    public const MODE_MINI = 1;
    public const MODE_NORMAL = 2;
    public const MODE_MAX = 3;

    public const DIR_UP = 0x01;
    public const DIR_MID = 0x02;
    public const DIR_DOWN = 0x04;

    private const CHARS_UP = [
        "\u{030d}", "\u{030e}", "\u{0304}", "\u{0305}", "\u{033f}",
        "\u{0311}", "\u{0306}", "\u{0310}", "\u{0352}", "\u{0357}",
        "\u{0351}", "\u{0307}", "\u{0308}", "\u{030a}", "\u{0342}",
        "\u{0344}", "\u{034a}", "\u{034b}", "\u{034c}", "\u{0303}",
        "\u{0302}", "\u{030c}", "\u{0350}", "\u{0300}", "\u{0301}",
        "\u{030b}", "\u{030f}", "\u{0312}", "\u{0313}", "\u{0314}",
        "\u{033d}", "\u{0309}", "\u{0363}", "\u{0364}", "\u{0365}",
        "\u{0366}", "\u{0367}", "\u{0368}", "\u{0369}", "\u{036a}",
        "\u{036b}", "\u{036c}", "\u{036d}", "\u{036e}", "\u{036f}",
        "\u{033e}", "\u{035b}", "\u{0346}", "\u{031a}",
    ];
    private const CHARS_DOWN = [
        "\u{0316}", "\u{0317}", "\u{0318}", "\u{0319}", "\u{031c}",
        "\u{031d}", "\u{031e}", "\u{031f}", "\u{0320}", "\u{0324}",
        "\u{0325}", "\u{0326}", "\u{0329}", "\u{032a}", "\u{032b}",
        "\u{032c}", "\u{032d}", "\u{032e}", "\u{032f}", "\u{0330}",
        "\u{0331}", "\u{0332}", "\u{0333}", "\u{0339}", "\u{033a}",
        "\u{033b}", "\u{033c}", "\u{0345}", "\u{0347}", "\u{0348}",
        "\u{0349}", "\u{034d}", "\u{034e}", "\u{0353}", "\u{0354}",
        "\u{0355}", "\u{0356}", "\u{0359}", "\u{035a}", "\u{0323}",
    ];
    private const CHARS_MIDDLE = [
        "\u{0315}", "\u{031b}", "\u{0340}", "\u{0341}", "\u{0358}",
        "\u{0321}", "\u{0322}", "\u{0327}", "\u{0328}", "\u{0334}",
        "\u{0335}", "\u{0336}", "\u{034f}", "\u{035c}", "\u{035d}",
        "\u{035e}", "\u{035f}", "\u{0360}", "\u{0362}", "\u{0338}",
        "\u{0337}", "\u{0361}", "\u{0489}",
    ];

    public static function strip(string $text): string {
        $text = str_replace(self::CHARS_UP, '', $text);
        $text = str_replace(self::CHARS_DOWN, '', $text);
        $text = str_replace(self::CHARS_MIDDLE, '', $text);
        return $text;
    }

    public static function isZalgoChar(string $char): bool {
        return in_array($char, self::CHARS_UP)
            || in_array($char, self::CHARS_DOWN)
            || in_array($char, self::CHARS_MIDDLE);
    }

    public static function getString(array $array, int $length): string {
        $string = '';

        for($i = 0; $i < $length; $i++)
            $string .= array_rand_value($array);

        return $string;
    }

    public static function run(
        string $text,
        int $mode = self::MODE_MINI,
        int $direction = self::DIR_MID | self::DIR_DOWN
    ): string {
        $length = mb_strlen($text);

        if($length < 1 || $mode < 1 || $direction < 1) {
            return $text;
        }

        $going_up   = ($direction & self::DIR_UP) > 0;
        $going_mid  = ($direction & self::DIR_MID) > 0;
        $going_down = ($direction & self::DIR_DOWN) > 0;

        $str = '';

        for($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if(self::isZalgoChar($char))
                continue;

            $str .= $char;

            switch($mode) {
                case self::MODE_MINI:
                    $num_up     = mt_rand(0, 8);
                    $num_mid    = mt_rand(0, 2);
                    $num_down   = mt_rand(0, 8);
                    break;

                case self::MODE_NORMAL:
                    $num_up     = mt_rand(0, 16) / 2 + 1;
                    $num_mid    = mt_rand(0, 6) / 2;
                    $num_down   = mt_rand(0, 8) / 2 + 1;
                    break;

                case self::MODE_MAX:
                    $num_up     = mt_rand(0, 64) / 4 + 3;
                    $num_mid    = mt_rand(0, 16) / 4 + 1;
                    $num_down   = mt_rand(0, 64) / 4 + 3;
                    break;
            }

            if($going_up)
                $str .= self::getString(self::CHARS_UP, $num_up);

            if($going_mid)
                $str .= self::getString(self::CHARS_MIDDLE, $num_mid);

            if($going_down)
                $str .= self::getString(self::CHARS_DOWN, $num_down);
        }

        return $str;
    }
}
