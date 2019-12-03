<?php
use Misuzu\Base32;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

define('MSZ_TOTP_DEFAULT_DIGITS', 6);
define('MSZ_TOTP_DEFAULT_ALGO', 'sha1');
define('MSZ_TOTP_DEFAULT_TOTP_INTERVAL', 30);

function otp_generate(
    int $data,
    string $secret,
    int $digits = MSZ_TOTP_DEFAULT_DIGITS,
    string $algo = MSZ_TOTP_DEFAULT_ALGO
): ?string {
    $hash = hash_hmac($algo, pack('J', $data), Base32::decode($secret), true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

    $bin = 0;
    $bin |= (ord($hash[$offset]) & 0x7F) << 24;
    $bin |= (ord($hash[$offset + 1]) & 0xFF) << 16;
    $bin |= (ord($hash[$offset + 2]) & 0xFF) << 8;
    $bin |= (ord($hash[$offset + 3]) & 0xFF);
    $otp = $bin % pow(10, $digits);

    return str_pad($otp, $digits, STR_PAD_LEFT);
}

function totp_timecode(int $timestamp, int $interval = MSZ_OTP_DEFAULT_TOTP_INTERVAL): int {
    return ($timestamp * 1000) / ($interval * 1000);
}

function totp_generate(
    string $secret,
    ?int $time = null,
    int $interval = MSZ_TOTP_DEFAULT_TOTP_INTERVAL,
    int $digits = MSZ_TOTP_DEFAULT_DIGITS,
    string $algo = MSZ_TOTP_DEFAULT_ALGO
): string {
    return otp_generate(totp_timecode($time ?? time(), $interval), $secret, $digits, $algo);
}

function totp_uri(string $name, string $secret, string $issuer = ''): string {
    $query = [
        'secret' => $secret,
    ];

    if(!empty($issuer)) {
        $query['issuer'] = $issuer;
    }

    return sprintf('otpauth://totp/%s?%s', $name, http_build_query($query));
}

function totp_qrcode(string $uri): string {
    $options = new QROptions([
        'version'    => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
    ]);
    $qrcode = new QRCode($options);

    return $qrcode->render($uri);
}

// will generate a 26 character code
function totp_generate_key(): string {
    return Base32::encode(random_bytes(16));
}
