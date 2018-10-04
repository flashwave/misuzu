<?php
define('MSZ_MAIL_STORE_OBJECT', '_msz_mail_swiftmailer');
define('MSZ_MAIL_STORE_OPTIONS', '_msz_mail_options');

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

function mail_prepare(array $options): void
{
    $GLOBALS[MSZ_MAIL_STORE_OPTIONS] = $options;
}

function mail_init_if_prepared(): bool
{
    return !empty($GLOBALS[MSZ_MAIL_STORE_OBJECT]) || (
        !empty($GLOBALS[MSZ_MAIL_STORE_OPTIONS]) && mail_init($GLOBALS[MSZ_MAIL_STORE_OPTIONS])
    );
}

function mail_init(array $options = []): bool
{
    if (!empty($GLOBALS[MSZ_MAIL_STORE_OBJECT])) {
        return true;
    }

    $GLOBALS[MSZ_MAIL_STORE_OPTIONS] = $options;
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

    $GLOBALS[MSZ_MAIL_STORE_OBJECT] = $transport;
    return true;
}

function mail_default_sender(): array
{
    return [
        $GLOBALS[MSZ_MAIL_STORE_OPTIONS]['sender_email'] ?? MSZ_MAIL_DEFAULT_SENDER_ADDRESS =>
        $GLOBALS[MSZ_MAIL_STORE_OPTIONS]['sender_name'] ?? MSZ_MAIL_DEFAULT_SENDER_NAME
    ];
}

function mail_send(Swift_Message $mail): int
{
    if (!mail_init_if_prepared()) {
        return 0;
    }

    return $GLOBALS[MSZ_MAIL_STORE_OBJECT]->send($mail);
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
