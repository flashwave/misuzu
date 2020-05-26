<?php
namespace Misuzu\Console\Commands;

use Misuzu\Config;
use Misuzu\Twitter;
use Misuzu\Console\CommandArgs;
use Misuzu\Console\CommandInterface;

class TwitterAuthCommand implements CommandInterface {
    public function getName(): string {
        return 'twitter-auth';
    }
    public function getSummary(): string {
        return 'Creates Twitter authentication tokens.';
    }

    public function dispatch(CommandArgs $args): void {
        $apiKey = Config::get('twitter.api.key', Config::TYPE_STR);
        $apiSecret = Config::get('twitter.api.secret', Config::TYPE_STR);

        if(empty($apiKey) || empty($apiSecret)) {
            echo 'No Twitter api keys set in config.' . PHP_EOL;
            return;
        }

        Twitter::init($apiKey, $apiSecret);
        echo 'Twitter Authentication' . PHP_EOL;

        $authPage = Twitter::createAuth();

        if(empty($authPage)) {
            echo 'Request to begin authentication failed.' . PHP_EOL;
            return;
        }

        echo 'Go to the page below and paste the pin code displayed.' . PHP_EOL . $authPage . PHP_EOL;

        $pin = readline('Pin: ');
        $authComplete = Twitter::completeAuth($pin);

        if(empty($authComplete)) {
            echo 'Invalid pin code.' . PHP_EOL;
            return;
        }

        echo 'Authentication successful!' . PHP_EOL
            . "Token: {$authComplete['token']}" . PHP_EOL
            . "Token Secret: {$authComplete['token_secret']}" . PHP_EOL;
    }
}
