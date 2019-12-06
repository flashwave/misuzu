<?php
namespace Misuzu;

final class Base64 {
    public static function encode(string $data, bool $url = false): string {
        $data = base64_encode($data);

        if($url)
            $data = rtrim(strtr($data, '+/', '-_'), '=');

        return $data;
    }

    public static function decode(string $data, bool $url = false): string {
        if($url)
            $data = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);

        return base64_decode($data);
    }

    public static function jsonEncode($data): string {
        return self::encode(json_encode($data), true);
    }

    public static function jsonDecode(string $data) {
        return json_decode(self::decode($data, true));
    }
}
