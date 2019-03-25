<?php
define('MSZ_USER_AVATAR_FORMAT', '%d.msz');
define('MSZ_USER_AVATAR_RESOLUTION_DEFAULT', 200);
define('MSZ_USER_AVATAR_RESOLUTION_ORIGINAL', 0);
define('MSZ_USER_AVATAR_RESOLUTIONS', [
    MSZ_USER_AVATAR_RESOLUTION_ORIGINAL,
    40, 60, 80, 100, 120, 200, 240,
]);

function user_avatar_valid_resolution(int $resolution): bool
{
    return in_array($resolution, MSZ_USER_AVATAR_RESOLUTIONS, true);
}

function user_avatar_resolution_closest(int $resolution): int
{
    if ($resolution === 0) {
        return MSZ_USER_AVATAR_RESOLUTION_ORIGINAL;
    }

    $closest = null;

    foreach (MSZ_USER_AVATAR_RESOLUTIONS as $res) {
        if ($res === MSZ_USER_AVATAR_RESOLUTION_ORIGINAL) {
            continue;
        }

        if ($closest === null || abs($resolution - $closest) >= abs($res - $resolution)) {
            $closest = $res;
        }
    }

    return $closest;
}

function user_avatar_delete(int $userId): void
{
    $avatarFileName = sprintf(MSZ_USER_AVATAR_FORMAT, $userId);
    $avatarPathFormat = MSZ_STORAGE . '/avatars/%s/%s';

    foreach (MSZ_USER_AVATAR_RESOLUTIONS as $res) {
        safe_delete(sprintf(
            $avatarPathFormat,
            $res === MSZ_USER_AVATAR_RESOLUTION_ORIGINAL
                ? 'original'
                : sprintf('%1$dx%1$d', $res),
            $avatarFileName
        ));
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
    'max_width' => 1000,
    'max_height' => 1000,
    'max_size' => 500000,
]);

function user_avatar_default_options(): array
{
    return array_merge(MSZ_USER_AVATAR_OPTIONS, config_get_default([], 'Avatar'));
}

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
    $storageDir = MSZ_STORAGE . '/avatars/original';
    mkdirs($storageDir, true);
    $avatarPath = "{$storageDir}/{$fileName}";

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
