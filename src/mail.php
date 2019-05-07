<?php
define('MSZ_MAIL_NULL', Swift_NullTransport::class);
define('MSZ_MAIL_SMTP', Swift_SmtpTransport::class);
define('MSZ_MAIL_SENDMAIL', Swift_SendmailTransport::class);
define('MSZ_MAIL_METHODS', [
    'null' => MSZ_MAIL_NULL,
    'smtp' => MSZ_MAIL_SMTP,
    'sendmail' => MSZ_MAIL_SENDMAIL,
]);

define('MSZ_MAIL_DEFAULT_SENDER_NAME', 'Misuzu System');
define('MSZ_MAIL_DEFAULT_SENDER_ADDRESS', 'sys@msz.lh');

function mail_settings($param = null)
{
    static $settings = [];

    if(!empty($param)) {
        if(is_array($param)) {
            $settings = array_merge_recursive($settings, $param);
        } elseif(is_string($param)) {
            return $settings[$param] ?? null;
        }
    }

    return $settings;
}

function mail_init_if_prepared(): bool
{
    return !empty(mail_instance()) || mail_init(mail_settings());
}

function mail_instance($newObject = null) {
    static $object = null;

    if(!empty($newObject)) {
        $object = $newObject;
    }

    return $object;
}

function mail_init(array $options = []): bool
{
    if (!empty(mail_instance())) {
        return true;
    }

    mail_settings($options);
    $method = $options['method'] ?? '';

    if (array_key_exists($method, MSZ_MAIL_METHODS)) {
        $method = MSZ_MAIL_METHODS[$method];
    }

    if (!in_array($method, MSZ_MAIL_METHODS)) {
        return false;
    }

    $transport = new $method;

    switch ($method) {
        case MSZ_MAIL_SENDMAIL:
            if (!empty($options['command'])) {
                $transport->setCommand($options['command']);
            }
            break;

        case MSZ_MAIL_SMTP:
            $transport->setHost($options['host'] ?? '');
            $transport->setPort(intval($options['port'] ?? 25));

            if (!empty($options['encryption'])) {
                $transport->setEncryption($options['encryption']);
            }

            if (!empty($options['username'])) {
                $transport->setUsername($options['username']);
            }

            if (!empty($options['password'])) {
                $transport->setPassword($options['password']);
            }
            break;
    }

    mail_instance($transport);
    return true;
}

function mail_default_sender(): array
{
    return [
        mail_settings('sender_email') ?? MSZ_MAIL_DEFAULT_SENDER_ADDRESS =>
        mail_settings('sender_name') ?? MSZ_MAIL_DEFAULT_SENDER_NAME
    ];
}

function mail_send(Swift_Message $mail): int
{
    if (!mail_init_if_prepared()) {
        return 0;
    }

    return mail_instance()->send($mail);
}

function mail_compose(
    array $addressees,
    string $subject,
    string $body
): Swift_Message {
    return (new Swift_Message($subject))
        ->setFrom(mail_default_sender())
        ->setTo($addressees)
        ->setBody($body);
}
