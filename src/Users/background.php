<?php
define('MSZ_USER_BACKGROUND_FORMAT', '%d.msz');

// attachment and attributes are to be stored in the same byte
// left half is for attributes, right half is for attachments
// this makes for 16 possible attachments and 4 possible attributes
// since attachments are just an incrementing number and attrs are flags

define('MSZ_USER_BACKGROUND_ATTACHMENT_NONE', 0);
define('MSZ_USER_BACKGROUND_ATTACHMENT_COVER', 1);
define('MSZ_USER_BACKGROUND_ATTACHMENT_STRETCH', 2);
define('MSZ_USER_BACKGROUND_ATTACHMENT_TILE', 3);
define('MSZ_USER_BACKGROUND_ATTACHMENT_CONTAIN', 4);

define('MSZ_USER_BACKGROUND_ATTACHMENTS', [
    MSZ_USER_BACKGROUND_ATTACHMENT_NONE,
    MSZ_USER_BACKGROUND_ATTACHMENT_COVER,
    MSZ_USER_BACKGROUND_ATTACHMENT_STRETCH,
    MSZ_USER_BACKGROUND_ATTACHMENT_TILE,
    MSZ_USER_BACKGROUND_ATTACHMENT_CONTAIN,
]);

define('MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES', [
    MSZ_USER_BACKGROUND_ATTACHMENT_COVER => 'cover',
    MSZ_USER_BACKGROUND_ATTACHMENT_STRETCH => 'stretch',
    MSZ_USER_BACKGROUND_ATTACHMENT_TILE => 'tile',
    MSZ_USER_BACKGROUND_ATTACHMENT_CONTAIN => 'contain',
]);

define('MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND', 0x10);
define('MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE', 0x20);

define('MSZ_USER_BACKGROUND_ATTRIBUTES', [
    MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND,
    MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE,
]);

define('MSZ_USER_BACKGROUND_ATTRIBUTES_NAMES', [
    MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND => 'blend',
    MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE => 'slide',
]);

function user_background_settings_strings(int $settings, string $format = '%s'): array {
    $arr = [];

    $attachment = $settings & 0x0F;

    if(array_key_exists($attachment, MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES)) {
        $arr[] = sprintf($format, MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES[$attachment]);
    }

    foreach(MSZ_USER_BACKGROUND_ATTRIBUTES_NAMES as $flag => $name) {
        if(($settings & $flag) > 0) {
            $arr[] = sprintf($format, $name);
        }
    }

    return $arr;
}

function user_background_set_settings(int $userId, int $settings): void {
    if($userId < 1) {
        return;
    }

    $setAttrs = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_background_settings` = :settings
        WHERE `user_id` = :user
    ');
    $setAttrs->bind('settings', $settings & 0xFF);
    $setAttrs->bind('user', $userId);
    $setAttrs->execute();
}

function user_background_delete(int $userId): void {
    $backgroundFileName = sprintf(MSZ_USER_BACKGROUND_FORMAT, $userId);
    safe_delete(MSZ_STORAGE . '/backgrounds/original/' . $backgroundFileName);
}

define('MSZ_USER_BACKGROUND_TYPE_PNG', IMAGETYPE_PNG);
define('MSZ_USER_BACKGROUND_TYPE_JPG', IMAGETYPE_JPEG);
define('MSZ_USER_BACKGROUND_TYPE_GIF', IMAGETYPE_GIF);
define('MSZ_USER_BACKGROUND_TYPES', [
    MSZ_USER_BACKGROUND_TYPE_PNG,
    MSZ_USER_BACKGROUND_TYPE_JPG,
    MSZ_USER_BACKGROUND_TYPE_GIF,
]);

function user_background_is_allowed_type(int $type): bool {
    return in_array($type, MSZ_USER_BACKGROUND_TYPES, true);
}

function user_background_default_options(): array {
    return [
        'max_width' => config_get('background.max_width', MSZ_CFG_INT, 3840),
        'max_height' => config_get('background.max_height', MSZ_CFG_INT, 2160),
        'max_size' => config_get('background.max_height', MSZ_CFG_INT, 1000000),
    ];
}

define('MSZ_USER_BACKGROUND_NO_ERRORS', 0);
define('MSZ_USER_BACKGROUND_ERROR_INVALID_IMAGE', 1);
define('MSZ_USER_BACKGROUND_ERROR_PROHIBITED_TYPE', 2);
define('MSZ_USER_BACKGROUND_ERROR_DIMENSIONS_TOO_LARGE', 3);
define('MSZ_USER_BACKGROUND_ERROR_DATA_TOO_LARGE', 4);
define('MSZ_USER_BACKGROUND_ERROR_TMP_FAILED', 5);
define('MSZ_USER_BACKGROUND_ERROR_STORE_FAILED', 6);
define('MSZ_USER_BACKGROUND_ERROR_FILE_NOT_FOUND', 7);

function user_background_set_from_path(int $userId, string $path, array $options = []): int {
    if(!file_exists($path)) {
        return MSZ_USER_BACKGROUND_ERROR_FILE_NOT_FOUND;
    }

    $options = array_merge(user_background_default_options(), $options);

    // 0 => width, 1 => height, 2 => type
    $imageInfo = getimagesize($path);

    if($imageInfo === false
        || count($imageInfo) < 3
        || $imageInfo[0] < 1
        || $imageInfo[1] < 1) {
        return MSZ_USER_BACKGROUND_ERROR_INVALID_IMAGE;
    }

    if(!user_background_is_allowed_type($imageInfo[2])) {
        return MSZ_USER_BACKGROUND_ERROR_PROHIBITED_TYPE;
    }

    if($imageInfo[0] > $options['max_width']
        || $imageInfo[1] > $options['max_height']) {
        return MSZ_USER_BACKGROUND_ERROR_DIMENSIONS_TOO_LARGE;
    }

    if(filesize($path) > $options['max_size']) {
        return MSZ_USER_BACKGROUND_ERROR_DATA_TOO_LARGE;
    }

    user_background_delete($userId);

    $fileName = sprintf(MSZ_USER_BACKGROUND_FORMAT, $userId);
    $storageDir = MSZ_STORAGE . '/backgrounds/original';
    mkdirs($storageDir, true);
    $backgroundPath = "{$storageDir}/{$fileName}";

    if(!copy($path, $backgroundPath)) {
        return MSZ_USER_BACKGROUND_ERROR_STORE_FAILED;
    }

    return MSZ_USER_BACKGROUND_NO_ERRORS;
}

function user_background_set_from_data(int $userId, string $data, array $options = []): int {
    $tmp = tempnam(sys_get_temp_dir(), 'msz');

    if($tmp === false || !file_exists($tmp)) {
        return MSZ_USER_BACKGROUND_ERROR_TMP_FAILED;
    }

    chmod($tmp, 644);
    file_put_contents($tmp, $data);
    $result = user_background_set_from_path($userId, $tmp, $options);
    safe_delete($tmp);

    return $result;
}
