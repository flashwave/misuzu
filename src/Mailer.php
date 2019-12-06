<?php
namespace Misuzu;

use InvalidArgumentException;
use Swift_Message;
use Swift_NullTransport;
use Swift_SmtpTransport;

final class Mailer {
    private const TEMPLATE_PATH = MSZ_ROOT . '/config/emails/%s.txt';

    private static $mailer = null;
    private static $mailerClass = '';
    private static $mailerConfig = [];

    public static function init(string $method, array $config): void {
        self::$mailerClass = $method === 'smtp' ? Swift_SmtpTransport::class : Swift_NullTransport::class;
        self::$mailerConfig = $config;
    }

    public static function getMailer() {
        if(self::$mailer === null) {
            self::$mailer = new self::$mailerClass;

            if(self::$mailerClass === Swift_SmtpTransport::class) {
                self::$mailer->setHost(self::$mailerConfig['host'] ?? '');
                self::$mailer->setPort(self::$mailerConfig['port'] ?? 25);
                self::$mailer->setUsername(self::$mailerConfig['username'] ?? '');
                self::$mailer->setPassword(self::$mailerConfig['password'] ?? '');
                self::$mailer->setEncryption(self::$mailerConfig['encryption'] ?? '');
            }
        }

        return self::$mailer;
    }

    public static function sendMessage(array $to, string $subject, string $contents, bool $bcc = false): bool {
        $message = new Swift_Message($subject);

        $message->setFrom([
            $config['sender_addr'] ?? 'sys@flashii.net' => $config['sender_name'] ?? 'Flashii',
        ]);

        if($bcc)
            $message->setBcc($to);
        else
            $message->setTo($to);

        $message->setBody($contents);

        return self::getMailer()->send($message);
    }

    public static function template(string $name, array $vars = []): array {
        $path = sprintf(self::TEMPLATE_PATH, $name);

        if(!is_file($path))
            throw new InvalidArgumentException('Invalid e-mail template name.');

        $tpl = file_get_contents($path);

        // Normalise newlines
        $tpl = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $tpl));

        foreach($vars as $key => $val)
            $tpl = str_replace("%{$key}%", $val, $tpl);

        [$subject, $message] = explode("\r\n\r\n", $tpl, 2);

        return compact('subject', 'message');
    }
}
