<?php
// Quick note to myself and others about the `display_role` column in the users database.
// Never ever EVER use it for ANYTHING other than determining display colours, there's a small chance that it might not be accurate.
// And even if it were, roles properties are aggregated and thus must all be accounted for.

define('MSZ_PERM_USER_EDIT_PROFILE', 1);
define('MSZ_PERM_USER_CHANGE_AVATAR', 1 << 1);
define('MSZ_PERM_USER_CHANGE_BACKGROUND', 1 << 2);
define('MSZ_PERM_USER_EDIT_ABOUT', 1 << 3);
define('MSZ_PERM_USER_EDIT_BIRTHDATE', 1 << 4);
define('MSZ_PERM_USER_EDIT_SIGNATURE', 1 << 5);

define('MSZ_PERM_USER_MANAGE_USERS', 1 << 20);
define('MSZ_PERM_USER_MANAGE_ROLES', 1 << 21);
define('MSZ_PERM_USER_MANAGE_PERMS', 1 << 22);
define('MSZ_PERM_USER_MANAGE_REPORTS', 1 << 23);
define('MSZ_PERM_USER_MANAGE_WARNINGS', 1 << 24);
//define('MSZ_PERM_USER_MANAGE_BLACKLISTS', 1 << 25); // Replaced with MSZ_PERM_GENERAL_MANAGE_BLACKLIST

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

function user_create(
    string $username,
    string $password,
    string $email,
    string $ipAddress
): int {
    $createUser = db_prepare('
        INSERT INTO `msz_users`
            (
                `username`, `password`, `email`, `register_ip`,
                `last_ip`, `user_country`, `display_role`
            )
        VALUES
            (
                :username, :password, LOWER(:email), INET6_ATON(:register_ip),
                INET6_ATON(:last_ip), :user_country, 1
            )
    ');
    $createUser->bindValue('username', $username);
    $createUser->bindValue('password', user_password_hash($password));
    $createUser->bindValue('email', $email);
    $createUser->bindValue('register_ip', $ipAddress);
    $createUser->bindValue('last_ip', $ipAddress);
    $createUser->bindValue('user_country', ip_country_code($ipAddress));

    return $createUser->execute() ? (int)db_last_insert_id() : 0;
}

function user_find_for_login(string $usernameOrMail): array
{
    $getUser = db_prepare('
        SELECT `user_id`, `password`
        FROM `msz_users`
        WHERE LOWER(`email`) = LOWER(:email)
        OR LOWER(`username`) = LOWER(:username)
    ');
    $getUser->bindValue('email', $usernameOrMail);
    $getUser->bindValue('username', $usernameOrMail);
    return db_fetch($getUser);
}

function user_find_for_reset(string $email): array
{
    $getUser = db_prepare('
        SELECT `user_id`, `username`, `email`
        FROM `msz_users`
        WHERE LOWER(`email`) = LOWER(:email)
    ');
    $getUser->bindValue('email', $email);
    return db_fetch($getUser);
}

function user_find_for_profile(string $idOrUsername): int
{
    $getUserId = db_prepare('
        SELECT
            :user_id as `input_id`,
            (
                SELECT `user_id`
                FROM `msz_users`
                WHERE `user_id` = `input_id`
                OR LOWER(`username`) = LOWER(`input_id`)
                LIMIT 1
            ) as `user_id`
    ');
    $getUserId->bindValue('user_id', $idOrUsername);
    return (int)($getUserId->execute() ? $getUserId->fetchColumn(1) : 0);
}

function user_password_hash(string $password): string
{
    return password_hash($password, MSZ_USERS_PASSWORD_HASH_ALGO);
}

function user_password_set(int $userId, string $password): bool
{
    $updatePassword = db_prepare('
        UPDATE `msz_users`
        SET `password` = :password
        WHERE `user_id` = :user
    ');
    $updatePassword->bindValue('user', $userId);
    $updatePassword->bindValue('password', user_password_hash($password));
    return $updatePassword->execute();
}

function user_email_get(int $userId): string
{
    if ($userId < 1) {
        return '';
    }

    $fetchMail = db_prepare('
        SELECT `email`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $fetchMail->bindValue('user_id', $userId);
    return $fetchMail->execute() ? (string)$fetchMail->fetchColumn() : '';
}

function user_email_set(int $userId, string $email): bool
{
    $updateMail = db_prepare('
        UPDATE `msz_users`
        SET `email` = LOWER(:email)
        WHERE `user_id` = :user
    ');
    $updateMail->bindValue('user', $userId);
    $updateMail->bindValue('email', $email);
    return $updateMail->execute();
}

function user_password_verify_db(int $userId, string $password): bool
{
    if ($userId < 1) {
        return false;
    }

    $fetchPassword = db_prepare('
        SELECT `password`
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $fetchPassword->bindValue('user_id', $userId);
    $currentPassword = $fetchPassword->execute() ? $fetchPassword->fetchColumn() : '';

    return !empty($currentPassword) && password_verify($password, $currentPassword);
}

// function of the century, only use this if it doesn't make sense to grab data otherwise
function user_exists(int $userId): bool
{
    if ($userId < 1) {
        return false;
    }

    $check = db_prepare('
        SELECT COUNT(`user_id`) > 0
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $check->bindValue('user_id', $userId);
    return $check->execute() ? (bool)$check->fetchColumn() : false;
}

function user_id_from_username(string $username): int
{
    $getId = db_prepare('SELECT `user_id` FROM `msz_users` WHERE LOWER(`username`) = LOWER(:username)');
    $getId->bindValue('username', $username);
    return $getId->execute() ? (int)$getId->fetchColumn() : 0;
}

function user_username_from_id(int $userId): string
{
    $getName = db_prepare('SELECT `username` FROM `msz_users` WHERE `user_id` = :user_id');
    $getName->bindValue('user_id', $userId);
    return $getName->execute() ? $getName->fetchColumn() : '';
}

function user_bump_last_active(int $userId, string $ipAddress = null): void
{
    $bumpUserLast = db_prepare('
        UPDATE `msz_users`
        SET `user_active` = NOW(),
            `last_ip` = INET6_ATON(:last_ip)
        WHERE `user_id` = :user_id
    ');
    $bumpUserLast->bindValue('last_ip', $ipAddress ?? ip_remote_address());
    $bumpUserLast->bindValue('user_id', $userId);
    $bumpUserLast->execute();
}

function user_get_last_ip(int $userId): string
{
    $getAddress = db_prepare('
        SELECT INET6_NTOA(`last_ip`)
        FROM `msz_users`
        WHERE `user_id` = :user_id
    ');
    $getAddress->bindValue('user_id', $userId);
    return $getAddress->execute() ? $getAddress->fetchColumn() : '';
}

function user_check_authority(int $userId, int $subjectId, bool $canManageSelf = true): bool
{
    if ($canManageSelf && $userId === $subjectId) {
        return true;
    }

    $checkHierarchy = db_prepare('
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
    $checkHierarchy->bindValue('user_id', $userId);
    $checkHierarchy->bindValue('subject_id', $subjectId);
    return (bool)($checkHierarchy->execute() ? $checkHierarchy->fetchColumn() : false);
}

function user_get_hierarchy(int $userId): int
{
    $getHierarchy = db_prepare('
        SELECT MAX(r.`role_hierarchy`)
        FROM `msz_roles` AS r
        LEFT JOIN `msz_user_roles` AS ur
        ON ur.`role_id` = r.`role_id`
        WHERE ur.`user_id` = :user_id
    ');
    $getHierarchy->bindValue('user_id', $userId);
    return (int)($getHierarchy->execute() ? $getHierarchy->fetchColumn() : 0);
}

define('MSZ_E_USER_BIRTHDATE_OK', 0);
define('MSZ_E_USER_BIRTHDATE_USER', 1);
define('MSZ_E_USER_BIRTHDATE_DATE', 2);
define('MSZ_E_USER_BIRTHDATE_FAIL', 3);
define('MSZ_E_USER_BIRTHDATE_YEAR', 4);

function user_set_birthdate(int $userId, int $day, int $month, int $year, int $yearRange = 100): int
{
    if ($userId < 1) {
        return MSZ_E_USER_BIRTHDATE_USER;
    }

    $unset = $day === 0 && $month === 0;

    if ($year === 0) {
        $checkYear = date('Y');
    } else {
        echo $year;
        if ($year < date('Y') - $yearRange || $year > date('Y')) {
            return MSZ_E_USER_BIRTHDATE_YEAR;
        }

        $checkYear = $year;
    }

    if (!$unset && !checkdate($month, $day, $checkYear)) {
        return MSZ_E_USER_BIRTHDATE_DATE;
    }

    $birthdate = $unset ? null : implode('-', [$year, $month, $day]);
    $setBirthdate = db_prepare('
        UPDATE `msz_users`
        SET `user_birthdate` = :birthdate
        WHERE `user_id` = :user
    ');
    $setBirthdate->bindValue('birthdate', $birthdate);
    $setBirthdate->bindValue('user', $userId);

    return $setBirthdate->execute()
        ? MSZ_E_USER_BIRTHDATE_OK
        : MSZ_E_USER_BIRTHDATE_FAIL;
}

function user_get_birthdays(int $day = 0, int $month = 0)
{
    if ($day < 1 || $month < 1) {
        $date = date('%-m-d');
    } else {
        $date = "%-{$month}-{$day}";
    }

    $getBirthdays = db_prepare('
        SELECT `user_id`, `username`, `user_birthdate`,
            IF(YEAR(`user_birthdate`) < 1, NULL, YEAR(NOW()) - YEAR(`user_birthdate`)) AS `user_age`
        FROM `msz_users`
        WHERE `user_deleted` IS NULL
        AND `user_birthdate` LIKE :birthdate
    ');
    $getBirthdays->bindValue('birthdate', $date);
    return db_fetch_all($getBirthdays);
}

define('MSZ_USER_ABOUT_MAX_LENGTH', 0xFFFF);

define('MSZ_E_USER_ABOUT_OK', 0);
define('MSZ_E_USER_ABOUT_INVALID_USER', 1);
define('MSZ_E_USER_ABOUT_INVALID_PARSER', 2);
define('MSZ_E_USER_ABOUT_TOO_LONG', 3);
define('MSZ_E_USER_ABOUT_UPDATE_FAILED', 4);

function user_set_about_page(int $userId, string $content, int $parser = MSZ_PARSER_PLAIN): int
{
    if ($userId < 1) {
        return MSZ_E_USER_ABOUT_INVALID_USER;
    }

    if (!parser_is_valid($parser)) {
        return MSZ_E_USER_ABOUT_INVALID_PARSER;
    }

    $length = strlen($content);

    if ($length > MSZ_USER_ABOUT_MAX_LENGTH) {
        return MSZ_E_USER_ABOUT_TOO_LONG;
    }

    $setAbout = db_prepare('
        UPDATE `msz_users`
        SET `user_about_content` = :content,
            `user_about_parser` = :parser
        WHERE `user_id` = :user
    ');
    $setAbout->bindValue('user', $userId);
    $setAbout->bindValue('content', $length < 1 ? null : $content);
    $setAbout->bindValue('parser', $parser);

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

function user_set_signature(int $userId, string $content, int $parser = MSZ_PARSER_PLAIN): int
{
    if ($userId < 1) {
        return MSZ_E_USER_SIGNATURE_INVALID_USER;
    }

    if (!parser_is_valid($parser)) {
        return MSZ_E_USER_SIGNATURE_INVALID_PARSER;
    }

    $length = strlen($content);

    if ($length > MSZ_USER_SIGNATURE_MAX_LENGTH) {
        return MSZ_E_USER_SIGNATURE_TOO_LONG;
    }

    $setSignature = db_prepare('
        UPDATE `msz_users`
        SET `user_signature_content` = :content,
            `user_signature_parser` = :parser
        WHERE `user_id` = :user
    ');
    $setSignature->bindValue('user', $userId);
    $setSignature->bindValue('content', $length < 1 ? null : $content);
    $setSignature->bindValue('parser', $parser);

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
            MSZ_USER_AVATAR_NO_ERRORS => '',
            MSZ_USER_AVATAR_ERROR_INVALID_IMAGE => 'The file you uploaded was not an image!',
            MSZ_USER_AVATAR_ERROR_PROHIBITED_TYPE => 'This type of image is not supported!',
            MSZ_USER_AVATAR_ERROR_DIMENSIONS_TOO_LARGE => 'Your background can\'t be larger than %3$dx%4$d!',
            MSZ_USER_AVATAR_ERROR_DATA_TOO_LARGE => 'Your background is not allowed to be larger in file size than %2$s!',
            MSZ_USER_AVATAR_ERROR_TMP_FAILED => 'Unable to save your background, contact an administator!',
            MSZ_USER_AVATAR_ERROR_STORE_FAILED => 'Unable to save your background, contact an administator!',
            MSZ_USER_AVATAR_ERROR_FILE_NOT_FOUND => 'Unable to save your background, contact an administator!',
        ],
    ],
    'profile' => [
        '_' => 'An unexpected error occurred, contact an administator.',
        'not-allowed' => "You're not allowed to edit your profile.",
        MSZ_USER_PROFILE_INVALID_FIELD => "Field '%1\$s' does not exist!",
        MSZ_USER_PROFILE_FILTER_FAILED => '%2$s field was invalid!',
        MSZ_USER_PROFILE_UPDATE_FAILED => 'Failed to update profile, contact an administator.',
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
