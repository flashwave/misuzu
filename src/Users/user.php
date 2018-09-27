<?php
use Misuzu\Application;
use Misuzu\Database;

define('MSZ_PERM_USER_EDIT_PROFILE', 1);
define('MSZ_PERM_USER_CHANGE_AVATAR', 1 << 1);
define('MSZ_PERM_USER_CHANGE_BACKGROUND', 1 << 2);
define('MSZ_PERM_USER_EDIT_ABOUT', 1 << 3);

define('MSZ_PERM_USER_MANAGE_USERS', 1 << 20);
define('MSZ_PERM_USER_MANAGE_ROLES', 1 << 21);
define('MSZ_PERM_USER_MANAGE_PERMS', 1 << 22);
define('MSZ_PERM_USER_MANAGE_REPORTS', 1 << 23);
define('MSZ_PERM_USER_MANAGE_RESTRICTIONS', 1 << 24);
define('MSZ_PERM_USER_MANAGE_BLACKLISTS', 1 << 25);

// falls back to bcrypt if argon2i isn't available, thanks travis ci
define('MSZ_USERS_PASSWORD_HASH_ALGO', defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_BCRYPT);

function user_create(
    string $username,
    string $password,
    string $email,
    string $ipAddress
): int {
    $createUser = Database::prepare('
        INSERT INTO `msz_users`
            (
                `username`, `password`, `email`, `register_ip`,
                `last_ip`, `user_country`, `created_at`, `display_role`
            )
        VALUES
            (
                :username, :password, LOWER(:email), INET6_ATON(:register_ip),
                INET6_ATON(:last_ip), :user_country, NOW(), 1
            )
    ');
    $createUser->bindValue('username', $username);
    $createUser->bindValue('password', user_password_hash($password));
    $createUser->bindValue('email', $email);
    $createUser->bindValue('register_ip', $ipAddress);
    $createUser->bindValue('last_ip', $ipAddress);
    $createUser->bindValue('user_country', ip_country_code($ipAddress));

    return $createUser->execute() ? (int)Database::lastInsertId() : 0;
}

function user_password_hash(string $password): string
{
    return password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO);
}

// function of the century, only use this if it doesn't make sense to grab data otherwise
function user_exists(int $userId): bool
{
    $check = Database::prepare('
        SELECT COUNT(`user_id`) > 0
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $check->bindValue('user_id', $userId);
    return $check->execute() ? (bool)$check->fetchColumn() : false;
}

function user_id_from_username(string $username): int
{
    $getId = Database::prepare('SELECT `user_id` FROM `msz_users` WHERE LOWER(`username`) = LOWER(:username)');
    $getId->bindValue('username', $username);
    return $getId->execute() ? (int)$getId->fetchColumn() : 0;
}

function user_bump_last_active(int $userId, string $ipAddress = null): void
{
    $bumpUserLast = Database::prepare('
        UPDATE `msz_users`
        SET `last_seen` = NOW(),
            `last_ip` = INET6_ATON(:last_ip)
        WHERE `user_id` = :user_id
    ');
    $bumpUserLast->bindValue('last_ip', $ipAddress ?? ip_remote_address());
    $bumpUserLast->bindValue('user_id', $userId);
    $bumpUserLast->execute();
}

define('MSZ_USER_AVATAR_FORMAT', '%d.msz');

function user_avatar_delete(int $userId): void
{
    $avatarFileName = sprintf(MSZ_USER_AVATAR_FORMAT, $userId);
    $storePath = Application::getInstance()->getStoragePath();

    $deleteThis = [
        build_path($storePath, 'avatars/original', $avatarFileName),
        build_path($storePath, 'avatars/200x200', $avatarFileName),
    ];

    foreach ($deleteThis as $deleteAvatar) {
        safe_delete($deleteAvatar);
    }
}

define('MSZ_USER_AVATAR_TYPE_PNG', IMAGETYPE_PNG);
define('MSZ_USER_AVATAR_TYPE_JPG', IMAGETYPE_JPEG);
define('MSZ_USER_AVATAR_TYPE_GIF', IMAGETYPE_GIF);
define('MSZ_USER_AVATAR_TYPES', [
    MSZ_USER_AVATAR_TYPE_PNG,
    MSZ_USER_AVATAR_TYPE_JPG,
    MSZ_USER_AVATAR_TYPE_GIF,
]);

function user_avatar_is_allowed_type(int $type): bool
{
    return in_array($type, MSZ_USER_AVATAR_TYPES, true);
}

define('MSZ_USER_AVATAR_OPTIONS', [
    'max_width' => 4000,
    'max_height' => 4000,
    'max_size' => 1000000,
]);

define('MSZ_USER_AVATAR_NO_ERRORS', 0);
define('MSZ_USER_AVATAR_ERROR_INVALID_IMAGE', 1);
define('MSZ_USER_AVATAR_ERROR_PROHIBITED_TYPE', 2);
define('MSZ_USER_AVATAR_ERROR_DIMENSIONS_TOO_LARGE', 3);
define('MSZ_USER_AVATAR_ERROR_DATA_TOO_LARGE', 4);
define('MSZ_USER_AVATAR_ERROR_TMP_FAILED', 5);
define('MSZ_USER_AVATAR_ERROR_STORE_FAILED', 6);
define('MSZ_USER_AVATAR_ERROR_FILE_NOT_FOUND', 7);

function user_avatar_set_from_path(int $userId, string $path, array $options = []): int
{
    if (!file_exists($path)) {
        return MSZ_USER_AVATAR_ERROR_FILE_NOT_FOUND;
    }

    $options = array_merge(MSZ_USER_AVATAR_OPTIONS, $options);

    // 0 => width, 1 => height, 2 => type
    $imageInfo = getimagesize($path);

    if ($imageInfo === false
        || count($imageInfo) < 3
        || $imageInfo[0] < 1
        || $imageInfo[1] < 1) {
        return MSZ_USER_AVATAR_ERROR_INVALID_IMAGE;
    }

    if (!user_avatar_is_allowed_type($imageInfo[2])) {
        return MSZ_USER_AVATAR_ERROR_PROHIBITED_TYPE;
    }

    if ($imageInfo[0] > $options['max_width']
        || $imageInfo[1] > $options['max_height']) {
        return MSZ_USER_AVATAR_ERROR_DIMENSIONS_TOO_LARGE;
    }

    if (filesize($path) > $options['max_size']) {
        return MSZ_USER_AVATAR_ERROR_DATA_TOO_LARGE;
    }

    user_avatar_delete($userId);

    $fileName = sprintf(MSZ_USER_AVATAR_FORMAT, $userId);
    $avatarPath = build_path(
        create_directory(build_path(Application::getInstance()->getStoragePath(), 'avatars/original')),
        $fileName
    );

    if (!copy($path, $avatarPath)) {
        return MSZ_USER_AVATAR_ERROR_STORE_FAILED;
    }

    return MSZ_USER_AVATAR_NO_ERRORS;
}

function user_avatar_set_from_data(int $userId, string $data, array $options = []): int
{
    $tmp = tempnam(sys_get_temp_dir(), 'msz');

    if ($tmp === false || !file_exists($tmp)) {
        return MSZ_USER_AVATAR_ERROR_TMP_FAILED;
    }

    chmod($tmp, 644);
    file_put_contents($tmp, $data);
    $result = user_avatar_set_from_path($userId, $tmp, $options);
    safe_delete($tmp);

    return $result;
}

define('MSZ_USER_BACKGROUND_FORMAT', '%d.msz');

function user_background_delete(int $userId): void
{
    $backgroundFileName = sprintf(MSZ_USER_BACKGROUND_FORMAT, $userId);
    $storePath = Application::getInstance()->getStoragePath();
    safe_delete(build_path($storePath, 'backgrounds/original', $backgroundFileName));
}

define('MSZ_USER_BACKGROUND_TYPE_PNG', IMAGETYPE_PNG);
define('MSZ_USER_BACKGROUND_TYPE_JPG', IMAGETYPE_JPEG);
define('MSZ_USER_BACKGROUND_TYPES', [
    MSZ_USER_BACKGROUND_TYPE_PNG,
    MSZ_USER_BACKGROUND_TYPE_JPG,
]);

function user_background_is_allowed_type(int $type): bool
{
    return in_array($type, MSZ_USER_BACKGROUND_TYPES, true);
}

define('MSZ_USER_BACKGROUND_OPTIONS', [
    'max_width' => 3840,
    'max_height' => 2160,
    'max_size' => 1000000,
]);

define('MSZ_USER_BACKGROUND_NO_ERRORS', 0);
define('MSZ_USER_BACKGROUND_ERROR_INVALID_IMAGE', 1);
define('MSZ_USER_BACKGROUND_ERROR_PROHIBITED_TYPE', 2);
define('MSZ_USER_BACKGROUND_ERROR_DIMENSIONS_TOO_LARGE', 3);
define('MSZ_USER_BACKGROUND_ERROR_DATA_TOO_LARGE', 4);
define('MSZ_USER_BACKGROUND_ERROR_TMP_FAILED', 5);
define('MSZ_USER_BACKGROUND_ERROR_STORE_FAILED', 6);
define('MSZ_USER_BACKGROUND_ERROR_FILE_NOT_FOUND', 7);

function user_background_set_from_path(int $userId, string $path, array $options = []): int
{
    if (!file_exists($path)) {
        return MSZ_USER_BACKGROUND_ERROR_FILE_NOT_FOUND;
    }

    $options = array_merge(MSZ_USER_BACKGROUND_OPTIONS, $options);

    // 0 => width, 1 => height, 2 => type
    $imageInfo = getimagesize($path);

    if ($imageInfo === false
        || count($imageInfo) < 3
        || $imageInfo[0] < 1
        || $imageInfo[1] < 1) {
        return MSZ_USER_BACKGROUND_ERROR_INVALID_IMAGE;
    }

    if (!user_background_is_allowed_type($imageInfo[2])) {
        return MSZ_USER_BACKGROUND_ERROR_PROHIBITED_TYPE;
    }

    if ($imageInfo[0] > $options['max_width']
        || $imageInfo[1] > $options['max_height']) {
        return MSZ_USER_BACKGROUND_ERROR_DIMENSIONS_TOO_LARGE;
    }

    if (filesize($path) > $options['max_size']) {
        return MSZ_USER_BACKGROUND_ERROR_DATA_TOO_LARGE;
    }

    user_background_delete($userId);

    $fileName = sprintf(MSZ_USER_BACKGROUND_FORMAT, $userId);
    $backgroundPath = build_path(
        create_directory(build_path(Application::getInstance()->getStoragePath(), 'backgrounds/original')),
        $fileName
    );

    if (!copy($path, $backgroundPath)) {
        return MSZ_USER_BACKGROUND_ERROR_STORE_FAILED;
    }

    return MSZ_USER_BACKGROUND_NO_ERRORS;
}

function user_background_set_from_data(int $userId, string $data, array $options = []): int
{
    $tmp = tempnam(sys_get_temp_dir(), 'msz');

    if ($tmp === false || !file_exists($tmp)) {
        return MSZ_USER_BACKGROUND_ERROR_TMP_FAILED;
    }

    chmod($tmp, 644);
    file_put_contents($tmp, $data);
    $result = user_background_set_from_path($userId, $tmp, $options);
    safe_delete($tmp);

    return $result;
}
