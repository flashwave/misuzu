<?php
define('MSZ_AUTH_TFA_TOKENS_SIZE', 16); // * 2

function user_auth_tfa_token_generate(): string
{
    return bin2hex(random_bytes(MSZ_AUTH_TFA_TOKENS_SIZE));
}

function user_auth_tfa_token_create(int $userId): string
{
    if ($userId < 1) {
        return '';
    }

    $token = user_auth_tfa_token_generate();

    $createToken = db_prepare('
        INSERT INTO `msz_auth_tfa`
            (`user_id`, `tfa_token`)
        VALUES
            (:user_id, :token)
    ');
    $createToken->bindValue('user_id', $userId);
    $createToken->bindValue('token', $token);

    if (!$createToken->execute()) {
        return '';
    }

    return $token;
}

function user_auth_tfa_token_invalidate(string $token): void
{
    $deleteToken = db_prepare('
        DELETE FROM `msz_auth_tfa`
        WHERE `tfa_token` = :token
    ');
    $deleteToken->bindValue('token', $token);
    $deleteToken->execute();
}

function user_auth_tfa_token_info(string $token): array
{
    $getTokenInfo = db_prepare('
        SELECT
            at.`user_id`, at.`tfa_token`, at.`tfa_created`, u.`user_totp_key`
        FROM `msz_auth_tfa` AS at
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = at.`user_id`
        WHERE at.`tfa_token` = :token
        AND at.`tfa_created` >= NOW() - INTERVAL 15 MINUTE
    ');
    $getTokenInfo->bindValue('token', $token);
    return db_fetch($getTokenInfo);
}
