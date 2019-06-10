<?php
// Minimum username length.
define('MSZ_USERNAME_MIN_LENGTH', 3);

// Maximum username length, unless your name is Flappyzor(WorldwideOnline2018).
define('MSZ_USERNAME_MAX_LENGTH', 16);

// Username character constraint.
define('MSZ_USERNAME_REGEX', '[A-Za-z0-9-_]+');
define('MSZ_USERNAME_REGEX_FULL', '#^' . MSZ_USERNAME_REGEX . '$#u');

// Minimum amount of unique characters for passwords.
define('MSZ_PASSWORD_MIN_UNIQUE', 6);

function user_validate_username(string $username, bool $checkInUse = false): string {
    $username_length = mb_strlen($username);

    if($username !== trim($username)) {
        return 'trim';
    }

    if($username_length < MSZ_USERNAME_MIN_LENGTH) {
        return 'short';
    }

    if($username_length > MSZ_USERNAME_MAX_LENGTH) {
        return 'long';
    }

    if(!preg_match(MSZ_USERNAME_REGEX_FULL, $username)) {
        return 'invalid';
    }

    if($checkInUse) {
        $getUser = db_prepare('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
            WHERE LOWER(`username`) = LOWER(:username)
        ');
        $getUser->bindValue('username', $username);
        $userId = $getUser->execute() ? $getUser->fetchColumn() : 0;

        if($userId > 0) {
            return 'in-use';
        }
    }

    return '';
}

function user_validate_check_mx_record(string $email): bool {
    $domain = mb_substr(mb_strstr($email, '@'), 1);
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

function user_validate_email(string $email, bool $checkInUse = false): string {
    if(filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'format';
    }

    if(!user_validate_check_mx_record($email)) {
        return 'dns';
    }

    if($checkInUse) {
        $getUser = db_prepare('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
            WHERE LOWER(`email`) = LOWER(:email)
        ');
        $getUser->bindValue('email', $email);
        $userId = $getUser->execute() ? $getUser->fetchColumn() : 0;

        if($userId > 0) {
            return 'in-use';
        }
    }

    return '';
}

function user_validate_password(string $password): string {
    if(unique_chars($password) < MSZ_PASSWORD_MIN_UNIQUE) {
        return 'weak';
    }

    return '';
}

define('MSZ_USER_USERNAME_VALIDATION_STRINGS', [
    'trim' => 'Your username may not start or end with spaces!',
    'short' => sprintf('Your username is too short, it has to be at least %d characters!', MSZ_USERNAME_MIN_LENGTH),
    'long' => sprintf("Your username is too long, it can't be longer than %d characters!", MSZ_USERNAME_MAX_LENGTH),
    'invalid' => 'Your username contains invalid characters.',
    'in-use' => 'This username is already taken!',
]);
