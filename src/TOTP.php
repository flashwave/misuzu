<?php
namespace Misuzu;

class TOTP {
    public const DIGITS = 6;
    public const INTERVAL = 30;
    public const HASH_ALGO = 'sha1';

    private $secretKey;

    public function __construct(string $secretKey) {
        $this->secretKey = $secretKey;
    }

    public static function generateKey(): string {
        return Base32::encode(random_bytes(16));
    }

    public static function timecode(?int $timestamp = null, int $interval = self::INTERVAL): int {
        $timestamp = $timestamp ?? time();
        return ($timestamp * 1000) / ($interval * 1000);
    }

    public function generate(?int $timestamp = null): string {
        $hash = hash_hmac(self::HASH_ALGO, pack('J', self::timecode($timestamp)), Base32::decode($this->secretKey), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $bin = 0;
        $bin |= (ord($hash[$offset]) & 0x7F) << 24;
        $bin |= (ord($hash[$offset + 1]) & 0xFF) << 16;
        $bin |= (ord($hash[$offset + 2]) & 0xFF) << 8;
        $bin |= (ord($hash[$offset + 3]) & 0xFF);
        $otp = $bin % pow(10, self::DIGITS);

        return str_pad($otp, self::DIGITS, STR_PAD_LEFT);
    }
}
