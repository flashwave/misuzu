<?php
namespace Misuzu;

use Codebird\Codebird;

final class Twitter {
    public static function init(
        string $apiKey,
        string $apiSecretKey,
        ?string $token = null,
        ?string $tokenSecret = null
    ): void {
        Codebird::setConsumerKey($apiKey, $apiSecretKey);

        if($token !== null && $tokenSecret !== null) {
            self::setToken($token, $tokenSecret);
        }
    }

    public static function setToken(string $token, string $tokenSecret): void {
        Codebird::getInstance()->setToken($token, $tokenSecret);
    }

    public static function createAuth(): ?string {
        $codebird = Codebird::getInstance();
        $reply = $codebird->oauth_requestToken([
            'oauth_callback' => 'oob',
        ]);

        if(!$reply)
            return null;

        self::setToken($reply->oauth_token, $reply->oauth_token_secret);

        return $codebird->oauth_authorize();
    }

    public static function completeAuth(string $pin): array {
        $reply = Codebird::getInstance()->oauth_accessToken([
            'oauth_verifier' => $pin,
        ]);

        if(!$reply)
            return [];

        self::setToken($reply->oauth_token, $reply->oauth_token_secret);

        return [
            'token' => $reply->oauth_token,
            'token_secret' => $reply->oauth_token_secret,
        ];
    }

    public static function sendTweet(string $text): void {
        Codebird::getInstance()->statuses_update([
            'status' => $text,
        ]);
    }
}
