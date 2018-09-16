<?php
use Misuzu\Database;

// Minimum username length.
define('MSZ_USERNAME_MIN_LENGTH', 3);

// Maximum username length, unless your name is Flappyzor(WorldwideOnline2018).
define('MSZ_USERNAME_MAX_LENGTH', 16);

// Username character constraint.
define('MSZ_USERNAME_REGEX', '[A-Za-z0-9-_]+');
define('MSZ_USERNAME_REGEX_FULL', '#^' . MSZ_USERNAME_REGEX . '$#u');

// Minimum entropy value for passwords.
define('MSZ_PASSWORD_MIN_ENTROPY', 32);

function user_validate_username(string $username, bool $checkInUse = false): string
{
    $username_length = mb_strlen($username);

    if ($username !== trim($username)) {
        return 'trim';
    }

    if ($username_length < MSZ_USERNAME_MIN_LENGTH) {
        return 'short';
    }

    if ($username_length > MSZ_USERNAME_MAX_LENGTH) {
        return 'long';
    }

    if (!preg_match(MSZ_USERNAME_REGEX_FULL, $username)) {
        return 'invalid';
    }

    if ($checkInUse) {
        $getUser = Database::prepare('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
            WHERE LOWER(`username`) = LOWER(:username)
        ');
        $getUser->bindValue('username', $username);
        $userId = $getUser->execute() ? $getUser->fetchColumn() : 0;

        if ($userId > 0) {
            return 'in-use';
        }
    }

    return '';
}

function user_validate_email(string $email, bool $checkInUse = false): string
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'format';
    }

    if (!check_mx_record($email)) {
        return 'dns';
    }

    if ($checkInUse) {
        $getUser = Database::prepare('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
            WHERE LOWER(`email`) = LOWER(:email)
        ');
        $getUser->bindValue('email', $email);
        $userId = $getUser->execute() ? $getUser->fetchColumn() : 0;

        if ($userId > 0) {
            return 'in-use';
        }
    }

    return '';
}

function user_validate_password(string $password): string
{
    if (password_entropy($password) < MSZ_PASSWORD_MIN_ENTROPY) {
        return 'weak';
    }

    return '';
}
