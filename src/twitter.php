<?php
use Codebird\Codebird;

function twitter_init(
    string $apiKey,
    string $apiSecretKey,
    ?string $token = null,
    ?string $tokenSecret = null
): void {
    Codebird::setConsumerKey($apiKey, $apiSecretKey);

    if (!is_null($token) && !is_null($tokenSecret)) {
        twitter_token_set($token, $tokenSecret);
    }
}

function twitter_token_set(string $token, string $tokenSecret): void
{
    Codebird::getInstance()->setToken($token, $tokenSecret);
}

function twitter_auth_create(): ?string
{
    $codebird = Codebird::getInstance();
    $reply = $codebird->oauth_requestToken([
        'oauth_callback' => 'oob',
    ]);

    if (!$reply) {
        return null;
    }

    twitter_token_set($reply->oauth_token, $reply->oauth_token_secret);

    return $codebird->oauth_authorize();
}

function twitter_auth_complete(string $pin): array
{
    $reply = Codebird::getInstance()->oauth_accessToken([
        'oauth_verifier' => $pin,
    ]);

    if (!$reply) {
        return [];
    }

    twitter_token_set($reply->oauth_token, $reply->oauth_token_secret);

    return [
        'token' => $reply->oauth_token,
        'token_secret' => $reply->oauth_token_secret,
    ];
}

function twitter_tweet_post(string $text): void
{
    Codebird::getInstance()->statuses_update([
        'status' => $text,
    ]);
}
