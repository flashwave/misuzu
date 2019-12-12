<?php
namespace Misuzu;

use Exception;
use InvalidArgumentException;

final class CSRF {
    public const TOLERANCE = 30 * 60;
    public const HASH_ALGO = 'sha1';
    public const EPOCH = 1575158400;

    private $timestamp = 0;
    private $tolerance = 0;

    private static $globalIdentity = '';
    private static $globalSecretKey = '';

    public function __construct(int $tolerance = self::TOLERANCE, ?int $timestamp = null) {
        $this->setTolerance($tolerance);
        $this->setTimestamp($timestamp ?? self::timestamp());
    }

    public static function timestamp(): int {
        return time() - self::EPOCH;
    }

    public static function setGlobalIdentity(string $identity): void {
        self::$globalIdentity = $identity;
    }
    public static function setGlobalSecretKey(string $secretKey): void {
        self::$globalSecretKey = $secretKey;
    }
    public static function validate(string $token, ?string $identity = null, ?string $secretKey = null): bool {
        try {
            return self::decode($token, $identity ?? self::$globalIdentity, $secretKey ?? self::$globalSecretKey)->isValid();
        } catch(Exception $ex) {
            return false;
        }
    }
    public static function token(?string $identity = null, int $tolerance = self::TOLERANCE, ?string $secretKey = null, ?int $timestamp = null): string {
        return (new static($tolerance, $timestamp))->encode($identity ?? self::$globalIdentity, $secretKey ?? self::$globalSecretKey);
    }

    // Should be replaced by filters eventually <
    public static function header(...$args): string {
        return 'X-Misuzu-CSRF: ' . self::token(...$args);
    }
    public static function validateRequest(?string $identity = null, ?string $secretKey = null): bool {
        if(isset($_SERVER['HTTP_X_MISUZU_CSRF'])) {
            $token = $_SERVER['HTTP_X_MISUZU_CSRF'];
        } elseif(isset($_REQUEST['_csrf']) && is_string($_REQUEST['_csrf'])) { // Change this to $_POST later, it should never appear in urls
            $token = $_REQUEST['_csrf'];
        } elseif(isset($_REQUEST['csrf']) && is_string($_REQUEST['csrf'])) {
            $token = $_REQUEST['csrf'];
        } else {
            return false;
        }

        return self::validate($token, $identity, $secretKey);
    }
    // >

    public static function decode(string $token, string $identity, string $secretKey): CSRF {
        $hash = substr($token, 12);
        $unpacked = unpack('Vtimestamp/vtolerance', hex2bin(substr($token, 0, 12)));

        if(empty($hash) || empty($unpacked['timestamp']) || empty($unpacked['tolerance']))
            throw new InvalidArgumentException('Invalid token provided.');

        $csrf = new static($unpacked['tolerance'], $unpacked['timestamp']);

        if(!hash_equals($csrf->getHash($identity, $secretKey), $hash))
            throw new InvalidArgumentException('Modified token.');

        return $csrf;
    }

    public function encode(string $identity, string $secretKey): string {
        $token = bin2hex(pack('Vv', $this->getTimestamp(), $this->getTolerance()));
        $token .= $this->getHash($identity, $secretKey);
        return $token;
    }

    public function getHash(string $identity, string $secretKey): string {
        return hash_hmac(self::HASH_ALGO, "{$identity}|{$this->getTimestamp()}|{$this->getTolerance()}", $secretKey);
    }

    public function getTimestamp(): int {
        return $this->timestamp;
    }
    public function setTimestamp(int $timestamp): self {
        if($timestamp < 0 || $timestamp > 0xFFFFFFFF)
            throw new InvalidArgumentException('Timestamp must be within the constaints of an unsigned 32-bit integer.');
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getTolerance(): int {
        return $this->tolerance;
    }
    public function setTolerance(int $tolerance): self {
        if($tolerance < 0 || $tolerance > 0xFFFF)
            throw new InvalidArgumentException('Tolerance must be within the constaints of an unsigned 16-bit integer.');
        $this->tolerance = $tolerance;
        return $this;
    }

    public function isValid(): bool {
        $currentTime = self::timestamp();
        return $currentTime >= $this->getTimestamp() && $currentTime <= $this->getTimestamp() + $this->getTolerance();
    }
}
