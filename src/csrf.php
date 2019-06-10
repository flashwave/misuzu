<?php
define('MSZ_CSRF_TOLERANCE', 30 * 60); // DO NOT EXCEED 16-BIT INTEGER SIZES, SHIT _WILL_ BREAK
define('MSZ_CSRF_HTML', '<input type="hidden" name="csrf" value="%1$s">');
define('MSZ_CSRF_HASH_ALGO', 'sha256');
define('MSZ_CSRF_TOKEN_LENGTH', 76); // 8 + 4 + 64

// the following three functions DO NOT depend on csrf_init().
// $identity = When the user is logged in I recommend just using their session key, otherwise IP will be fine.
function csrf_token_create(
    string $identity,
    string $secretKey,
    ?int $timestamp = null,
    int $tolerance = MSZ_CSRF_TOLERANCE
): string {
    $timestamp = $timestamp ?? time();
    $token = bin2hex(pack('Vv', $timestamp, $tolerance));

    return $token . csrf_token_hash(
        MSZ_CSRF_HASH_ALGO,
        $identity,
        $secretKey,
        $timestamp,
        $tolerance
    );
}

function csrf_token_hash(
    string $algo,
    string $identity,
    string $secretKey,
    int $timestamp,
    int $tolerance
): string {
    return hash_hmac(
        $algo,
        implode(',', [$identity, $timestamp, $tolerance]),
        $secretKey
    );
}

function csrf_token_verify(
    string $token,
    string $identity,
    string $secretKey
): bool {
    if(empty($token) || strlen($token) !== MSZ_CSRF_TOKEN_LENGTH) {
        return false;
    }

    [$timestamp, $tolerance] = [0, 0];
    extract(unpack('Vtimestamp/vtolerance', hex2bin(substr($token, 0, 12))));

    if(time() > $timestamp + $tolerance) {
        return false;
    }

    // remove timestamp + tolerance from token
    $token = substr($token, 12);

    $compare = csrf_token_hash(
        MSZ_CSRF_HASH_ALGO,
        $identity,
        $secretKey,
        $timestamp,
        $tolerance
    );

    return hash_equals($compare, $token);
}

// Sets some defaults
function csrf_settings(?string $secretKey = null, ?string $identity = null): array {
    static $settings = [];

    if(!empty($secretKey) && !empty($identity)) {
        $settings = [
            'secret_key' => $secretKey,
            'identity' => $identity,
        ];
    }

    return $settings;
}

function csrf_is_ready(): bool {
    return !empty(csrf_settings());
}

function csrf_token(): string {
    static $token = null;

    if(empty($token)) {
        $settings = csrf_settings();
        $token = csrf_token_create(
            $settings['identity'],
            $settings['secret_key']
        );
    }

    return $token;
}

function csrf_verify(string $token): bool {
    $settings = csrf_settings();

    return csrf_token_verify(
        $token,
        $settings['identity'],
        $settings['secret_key']
    );
}

function csrf_verify_request(?string $token = null): bool {
    if(empty($token)) {
        $token = $_SERVER['HTTP_X_MISUZU_CSRF'] ?? $_REQUEST['csrf'] ?? '';
    }

    return csrf_verify($token);
}

function csrf_html(): string {
    return sprintf(MSZ_CSRF_HTML, csrf_token());
}

function csrf_http_header(string $name = 'X-Misuzu-CSRF'): void {
    header("{$name}: " . csrf_token());
}
