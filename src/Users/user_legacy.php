<?php
// Quick note to myself and others about the `display_role` column in the users database.
// Never ever EVER use it for ANYTHING other than determining display colours, there's a small chance that it might not be accurate.
// And even if it were, roles properties are aggregated and thus must all be accounted for.

define(
    'MSZ_USERS_PASSWORD_HASH_ALGO',
    defined('PASSWORD_ARGON2ID')
    ? PASSWORD_ARGON2ID
    : (
        defined('PASSWORD_ARGON2I')
        ? PASSWORD_ARGON2I
        : PASSWORD_BCRYPT
    )
);

function user_find_for_reset(string $email): array {
    $getUser = \Misuzu\DB::prepare('
        SELECT `user_id`, `username`, `email`
        FROM `msz_users`
        WHERE LOWER(`email`) = LOWER(:email)
        AND `user_deleted` IS NULL
    ');
    $getUser->bind('email', $email);
    return $getUser->fetch();
}

function user_password_hash(string $password): string {
    return password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO);
}

function user_password_set(int $userId, string $password): bool {
    $updatePassword = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `password` = :password
        WHERE `user_id` = :user
    ');
    $updatePassword->bind('user', $userId);
    $updatePassword->bind('password', user_password_hash($password));
    return $updatePassword->execute();
}

function user_totp_info(int $userId): array {
    if($userId < 1)
        return [];

    $getTwoFactorInfo = \Misuzu\DB::prepare('
        SELECT
            `username`, `user_totp_key`,
            `user_totp_key` IS NOT NULL AS `totp_enabled`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $getTwoFactorInfo->bind('user_id', $userId);

    return $getTwoFactorInfo->fetch();
}

function user_totp_update(int $userId, ?string $key): void {
    if($userId < 1)
        return;

    $key = empty($key) ? null : $key;

    $updateTotpKey = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_totp_key` = :key
        WHERE `user_id` = :user_id
    ');
    $updateTotpKey->bind('user_id', $userId);
    $updateTotpKey->bind('key', $key);
    $updateTotpKey->execute();
}

function user_email_get(int $userId): string {
    if($userId < 1)
        return '';

    $fetchMail = \Misuzu\DB::prepare('
        SELECT `email`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $fetchMail->bind('user_id', $userId);
    return (string)$fetchMail->fetchColumn(0, '');
}

function user_email_set(int $userId, string $email): bool {
    $updateMail = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `email` = LOWER(:email)
        WHERE `user_id` = :user
    ');
    $updateMail->bind('user', $userId);
    $updateMail->bind('email', $email);
    return $updateMail->execute();
}

function user_id_from_username(string $username): int {
    $getId = \Misuzu\DB::prepare('SELECT `user_id` FROM `msz_users` WHERE LOWER(`username`) = LOWER(:username)');
    $getId->bind('username', $username);
    return (int)$getId->fetchColumn(0, 0);
}

function user_username_from_id(int $userId): string {
    $getName = \Misuzu\DB::prepare('SELECT `username` FROM `msz_users` WHERE `user_id` = :user_id');
    $getName->bind('user_id', $userId);
    return $getName->fetchColumn(0, '');
}

function user_bump_last_active(int $userId, string $ipAddress = null): void {
    $bumpUserLast = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_active` = NOW(),
            `last_ip` = INET6_ATON(:last_ip)
        WHERE `user_id` = :user_id
    ');
    $bumpUserLast->bind('last_ip', $ipAddress ?? \Misuzu\Net\IPAddress::remote());
    $bumpUserLast->bind('user_id', $userId);
    $bumpUserLast->execute();
}

function user_get_last_ip(int $userId): string {
    $getAddress = \Misuzu\DB::prepare('
        SELECT INET6_NTOA(`last_ip`)
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $getAddress->bind('user_id', $userId);
    return $getAddress->fetchColumn(0, '');
}

function user_check_super(int $userId): bool {
    static $superUsers = [];

    if(!isset($superUsers[$userId])) {
        $checkSuperUser = \Misuzu\DB::prepare("
            SELECT `user_super`
            FROM `msz_users`
            WHERE `user_id` = :user_id
        ");
        $checkSuperUser->bind('user_id', $userId);
        $superUsers[$userId] = (bool)$checkSuperUser->fetchColumn(0, false);
    }

    return $superUsers[$userId];
}

function user_check_authority(int $userId, int $subjectId, bool $canManageSelf = true): bool {
    if($canManageSelf && $userId === $subjectId)
        return true;

    $checkHierarchy = \Misuzu\DB::prepare('
        SELECT (
            SELECT MAX(r.`role_hierarchy`)
            FROM `msz_roles` AS r
            LEFT JOIN `msz_user_roles` AS ur
            ON ur.`role_id` = r.`role_id`
            WHERE ur.`user_id` = :user_id
        ) > (
            SELECT MAX(r.`role_hierarchy`)
            FROM `msz_roles` AS r
            LEFT JOIN `msz_user_roles` AS ur
            ON ur.`role_id` = r.`role_id`
            WHERE ur.`user_id` = :subject_id
        )
    ');
    $checkHierarchy->bind('user_id', $userId);
    $checkHierarchy->bind('subject_id', $subjectId);
    return (bool)$checkHierarchy->fetchColumn(0, false);
}

function user_get_hierarchy(int $userId): int {
    $getHierarchy = \Misuzu\DB::prepare('
        SELECT MAX(r.`role_hierarchy`)
        FROM `msz_roles` AS r
        LEFT JOIN `msz_user_roles` AS ur
        ON ur.`role_id` = r.`role_id`
        WHERE ur.`user_id` = :user_id
    ');
    $getHierarchy->bind('user_id', $userId);
    return (int)$getHierarchy->fetchColumn(0, 0);
}

define('MSZ_E_USER_BIRTHDATE_OK', 0);
define('MSZ_E_USER_BIRTHDATE_USER', 1);
define('MSZ_E_USER_BIRTHDATE_DATE', 2);
define('MSZ_E_USER_BIRTHDATE_FAIL', 3);
define('MSZ_E_USER_BIRTHDATE_YEAR', 4);

function user_set_birthdate(int $userId, int $day, int $month, int $year, int $yearRange = 100): int {
    if($userId < 1)
        return MSZ_E_USER_BIRTHDATE_USER;

    $unset = $day === 0 && $month === 0;

    if($year === 0) {
        $checkYear = date('Y');
    } else {
        if($year < date('Y') - $yearRange || $year > date('Y'))
            return MSZ_E_USER_BIRTHDATE_YEAR;

        $checkYear = $year;
    }

    if(!$unset && !checkdate($month, $day, $checkYear))
        return MSZ_E_USER_BIRTHDATE_DATE;

    $birthdate = $unset ? null : implode('-', [$year, $month, $day]);
    $setBirthdate = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_birthdate` = :birthdate
        WHERE `user_id` = :user
    ');
    $setBirthdate->bind('birthdate', $birthdate);
    $setBirthdate->bind('user', $userId);

    return $setBirthdate->execute()
        ? MSZ_E_USER_BIRTHDATE_OK
        : MSZ_E_USER_BIRTHDATE_FAIL;
}

function user_get_birthdays(int $day = 0, int $month = 0) {
    $date = ($day < 1 || $month < 1) ? date('%-m-d') : "%-{$month}-{$day}";

    $getBirthdays = \Misuzu\DB::prepare('
        SELECT `user_id`, `username`, `user_birthdate`,
            IF(YEAR(`user_birthdate`) < 1, NULL, YEAR(NOW()) - YEAR(`user_birthdate`)) AS `user_age`
        FROM `msz_users`
        WHERE `user_deleted` IS NULL
        AND `user_birthdate` LIKE :birthdate
    ');
    $getBirthdays->bind('birthdate', $date);
    return $getBirthdays->fetchAll();
}

define('MSZ_USER_ABOUT_MAX_LENGTH', 0xFFFF);

define('MSZ_E_USER_ABOUT_OK', 0);
define('MSZ_E_USER_ABOUT_INVALID_USER', 1);
define('MSZ_E_USER_ABOUT_INVALID_PARSER', 2);
define('MSZ_E_USER_ABOUT_TOO_LONG', 3);
define('MSZ_E_USER_ABOUT_UPDATE_FAILED', 4);

function user_set_about_page(int $userId, string $content, int $parser = \Misuzu\Parsers\Parser::PLAIN): int {
    if($userId < 1)
        return MSZ_E_USER_ABOUT_INVALID_USER;

    if(!\Misuzu\Parsers\Parser::isValid($parser))
        return MSZ_E_USER_ABOUT_INVALID_PARSER;

    $length = strlen($content);

    if($length > MSZ_USER_ABOUT_MAX_LENGTH)
        return MSZ_E_USER_ABOUT_TOO_LONG;

    $setAbout = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_about_content` = :content,
            `user_about_parser` = :parser
        WHERE `user_id` = :user
    ');
    $setAbout->bind('user', $userId);
    $setAbout->bind('content', $length < 1 ? null : $content);
    $setAbout->bind('parser', $parser);

    return $setAbout->execute()
        ? MSZ_E_USER_ABOUT_OK
        : MSZ_E_USER_ABOUT_UPDATE_FAILED;
}

define('MSZ_USER_SIGNATURE_MAX_LENGTH', 2000);

define('MSZ_E_USER_SIGNATURE_OK', 0);
define('MSZ_E_USER_SIGNATURE_INVALID_USER', 1);
define('MSZ_E_USER_SIGNATURE_INVALID_PARSER', 2);
define('MSZ_E_USER_SIGNATURE_TOO_LONG', 3);
define('MSZ_E_USER_SIGNATURE_UPDATE_FAILED', 4);

function user_set_signature(int $userId, string $content, int $parser = \Misuzu\Parsers\Parser::PLAIN): int {
    if($userId < 1)
        return MSZ_E_USER_SIGNATURE_INVALID_USER;

    if(!\Misuzu\Parsers\Parser::isValid($parser))
        return MSZ_E_USER_SIGNATURE_INVALID_PARSER;

    $length = strlen($content);

    if($length > MSZ_USER_SIGNATURE_MAX_LENGTH)
        return MSZ_E_USER_SIGNATURE_TOO_LONG;

    $setSignature = \Misuzu\DB::prepare('
        UPDATE `msz_users`
        SET `user_signature_content` = :content,
            `user_signature_parser` = :parser
        WHERE `user_id` = :user
    ');
    $setSignature->bind('user', $userId);
    $setSignature->bind('content', $length < 1 ? null : $content);
    $setSignature->bind('parser', $parser);

    return $setSignature->execute()
        ? MSZ_E_USER_SIGNATURE_OK
        : MSZ_E_USER_SIGNATURE_UPDATE_FAILED;
}

// all the way down here bc of defines, this define is temporary
define('MSZ_TMP_USER_ERROR_STRINGS', [
    'csrf' => "Couldn't verify you, please refresh the page and retry.",
    'avatar' => [
        'not-allowed' => "You aren't allow to change your avatar.",
        'upload' => [
            '_' => 'Something happened? (UP:%1$d)',
            UPLOAD_ERR_OK => '',
            UPLOAD_ERR_NO_FILE => 'Select a file before hitting upload!',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted, please try again!',
            UPLOAD_ERR_INI_SIZE => 'Your avatar is not allowed to be larger in file size than %2$s!',
            UPLOAD_ERR_FORM_SIZE => 'Your avatar is not allowed to be larger in file size than %2$s!',
            UPLOAD_ERR_NO_TMP_DIR => 'Unable to save your avatar, contact an administator!',
            UPLOAD_ERR_CANT_WRITE => 'Unable to save your avatar, contact an administator!',
        ],
        'set' => [
            '_' => 'Something happened? (SET:%1$d)',
            MSZ_USER_AVATAR_NO_ERRORS => '',
            MSZ_USER_AVATAR_ERROR_INVALID_IMAGE => 'The file you uploaded was not an image!',
            MSZ_USER_AVATAR_ERROR_PROHIBITED_TYPE => 'This type of image is not supported, keep to PNG, JPG or GIF!',
            MSZ_USER_AVATAR_ERROR_DIMENSIONS_TOO_LARGE => 'Your avatar can\'t be larger than %3$dx%4$d!',
            MSZ_USER_AVATAR_ERROR_DATA_TOO_LARGE => 'Your avatar is not allowed to be larger in file size than %2$s!',
            MSZ_USER_AVATAR_ERROR_TMP_FAILED => 'Unable to save your avatar, contact an administator!',
            MSZ_USER_AVATAR_ERROR_STORE_FAILED => 'Unable to save your avatar, contact an administator!',
            MSZ_USER_AVATAR_ERROR_FILE_NOT_FOUND => 'Unable to save your avatar, contact an administator!',
        ],
    ],
    'background' => [
        'not-allowed' => "You aren't allow to change your background.",
        'upload' => [
            '_' => 'Something happened? (UP:%1$d)',
            UPLOAD_ERR_OK => '',
            UPLOAD_ERR_NO_FILE => 'Select a file before hitting upload!',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted, please try again!',
            UPLOAD_ERR_INI_SIZE => 'Your background is not allowed to be larger in file size than %2$s!',
            UPLOAD_ERR_FORM_SIZE => 'Your background is not allowed to be larger in file size than %2$s!',
            UPLOAD_ERR_NO_TMP_DIR => 'Unable to save your background, contact an administator!',
            UPLOAD_ERR_CANT_WRITE => 'Unable to save your background, contact an administator!',
        ],
        'set' => [
            '_' => 'Something happened? (SET:%1$d)',
            MSZ_USER_BACKGROUND_NO_ERRORS => '',
            MSZ_USER_BACKGROUND_ERROR_INVALID_IMAGE => 'The file you uploaded was not an image!',
            MSZ_USER_BACKGROUND_ERROR_PROHIBITED_TYPE => 'This type of image is not supported!',
            MSZ_USER_BACKGROUND_ERROR_DIMENSIONS_TOO_LARGE => 'Your background can\'t be larger than %3$dx%4$d!',
            MSZ_USER_BACKGROUND_ERROR_DATA_TOO_LARGE => 'Your background is not allowed to be larger in file size than %2$s!',
            MSZ_USER_BACKGROUND_ERROR_TMP_FAILED => 'Unable to save your background, contact an administator!',
            MSZ_USER_BACKGROUND_ERROR_STORE_FAILED => 'Unable to save your background, contact an administator!',
            MSZ_USER_BACKGROUND_ERROR_FILE_NOT_FOUND => 'Unable to save your background, contact an administator!',
        ],
    ],
    'profile' => [
        '_' => 'An unexpected error occurred, contact an administator.',
        'not-allowed' => "You're not allowed to edit your profile.",
        'invalid' => '%s was formatted incorrectly!',
    ],
    'about' => [
        '_' => 'An unexpected error occurred, contact an administator.',
        'not-allowed' => "You're not allowed to edit your about page.",
        MSZ_E_USER_ABOUT_INVALID_USER => 'The requested user does not exist.',
        MSZ_E_USER_ABOUT_INVALID_PARSER => 'The selected parser is invalid.',
        MSZ_E_USER_ABOUT_TOO_LONG => 'Please keep the length of your about section below %1$d characters.',
        MSZ_E_USER_ABOUT_UPDATE_FAILED => 'Failed to update about section, contact an administator.',
    ],
    'signature' => [
        '_' => 'An unexpected error occurred, contact an administator.',
        'not-allowed' => "You're not allowed to edit your about page.",
        MSZ_E_USER_SIGNATURE_INVALID_USER => 'The requested user does not exist.',
        MSZ_E_USER_SIGNATURE_INVALID_PARSER => 'The selected parser is invalid.',
        MSZ_E_USER_SIGNATURE_TOO_LONG => 'Please keep the length of your signature below %1$d characters.',
        MSZ_E_USER_SIGNATURE_UPDATE_FAILED => 'Failed to update signature, contact an administator.',
    ],
]);
