<?php
define('MSZ_CSRF_TOLERANCE', 30 * 60); // DO NOT EXCEED 16-BIT INTEGER SIZES, SHIT _WILL_ BREAK
define('MSZ_CSRF_HTML', '<input type="hidden" name="%1$s[%3$s]" value="%2$s">');
define('MSZ_CSRF_HASH_ALGO', 'sha256');
define('MSZ_CSRF_TOKEN_LENGTH', 76); // 8 + 4 + 64

// the following three functions DO NOT depend on csrf_init().
// $realm = Some kinda identifier for whatever's trying to do a validation.
// $identity = When the user is logged in I recommend just using their session key, otherwise IP will be fine.
function csrf_token_create(
    string $realm,
    string $identity,
    string $secretKey,
    ?int $timestamp = null,
    int $tolerance = MSZ_CSRF_TOLERANCE
): string {
    $timestamp = $timestamp ?? time();
    $token = bin2hex(pack('Vv', $timestamp, $tolerance));

    return $token . csrf_token_hash(
        MSZ_CSRF_HASH_ALGO,
        $realm,
        $identity,
        $secretKey,
        $timestamp,
        $tolerance
    );
}

function csrf_token_hash(
    string $algo,
    string $realm,
    string $identity,
    string $secretKey,
    int $timestamp,
    int $tolerance
): string {
    return hash_hmac(
        $algo,
        implode(',', [$realm, $identity, $timestamp, $tolerance]),
        $secretKey
    );
}

function csrf_token_verify(
    string $realm,
    string $token,
    string $identity,
    string $secretKey
): bool {
    if (empty($token) || strlen($token) !== MSZ_CSRF_TOKEN_LENGTH) {
        return false;
    }

    [$timestamp, $tolerance] = [0, 0];
    extract(unpack('Vtimestamp/vtolerance', hex2bin(substr($token, 0, 12))));

    if (time() > $timestamp + $tolerance) {
        return false;
    }

    // remove timestamp + tolerance from token
    $token = substr($token, 12);

    $compare = csrf_token_hash(
        MSZ_CSRF_HASH_ALGO,
        $realm,
        $identity,
        $secretKey,
        $timestamp,
        $tolerance
    );

    return hash_equals($compare, $token);
}

// Sets some defaults
function csrf_settings(?string $secretKey = null, ?string $identity = null): array
{
    static $settings = [];

    if(!empty($secretKey) && !empty($identity)) {
        $settings = [
            'secret_key' => $secretKey,
            'identity' => $identity,
        ];
    }

    return $settings;
}

function csrf_cache(?string $realm = null, $token = null) //: string|array
{
    static $store = [];

    if(!empty($realm)) {
        if(empty($store[$realm]) && !empty($token)) {
            if(is_callable($token)) {
                $store[$realm] = $token();
            } elseif (is_string($token)) {
                $store[$realm] = $token();
            }
        }

        return $store[$realm] ?? '';
    }

    return $store;
}

function csrf_is_ready(): bool
{
    return !empty(csrf_settings());
}

function csrf_token(string $realm): string
{
    return csrf_cache($realm, function() use ($realm) {
        $settings = csrf_settings();

        return csrf_token_create(
            $realm,
            $settings['identity'],
            $settings['secret_key']
        );
    });
}

function csrf_verify(string $realm, $token): bool
{
    $token = is_array($token) && !empty($token[$realm]) ? $token[$realm] : $token;

    if (!is_string($token)) {
        return false;
    }

    $settings = csrf_settings();

    return csrf_token_verify(
        $realm,
        $token,
        $settings['identity'],
        $settings['secret_key']
    );
}

function csrf_html(string $realm, string $name = 'csrf'): string
{
    return sprintf(MSZ_CSRF_HTML, $name, csrf_token($realm), $realm);
}

function csrf_http_header(string $realm, string $name = 'X-Misuzu-CSRF'): string
{
    return "{$name}: {$realm};" . csrf_token($realm);
}

function csrf_http_header_parse(string $header): array
{
    $split = explode(';', $header, 2);
    $realm = $split[0] ?? '';
    $token = $split[1] ?? '';

    if (empty($realm) || empty($token)) {
        [$realm, $token] = ['', ''];
    }

    return [
        'realm' => $realm,
        'token' => $token,
    ];
}

function csrf_get_list(): array
{
    $list = [];

    foreach (csrf_cache() as $realm => $token) {
        $list[] = compact('realm', 'token');
    }

    return $list;
}
