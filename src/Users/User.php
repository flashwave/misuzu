<?php
namespace Misuzu\Users;

use DateTime;
use DateTimeZone;
use JsonSerializable;
use Misuzu\Colour;
use Misuzu\DB;
use Misuzu\HasRankInterface;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\TOTP;
use Misuzu\Net\IPAddress;
use Misuzu\Parsers\Parser;
use Misuzu\Users\Assets\UserAvatarAsset;
use Misuzu\Users\Assets\UserBackgroundAsset;

class UserException extends UsersException {} // this naming definitely won't lead to confusion down the line!
class UserNotFoundException extends UserException {}
class UserCreationFailedException extends UserException {}

// Quick note to myself and others about the `display_role` column in the users database and its corresponding methods in this class.
// Never ever EVER use it for ANYTHING other than determining display colours, there's a small chance that it might not be accurate.
// And even if it were, roles properties are aggregated and thus must all be accounted for.

// TODO
// - Search for comments starting with TODO
// - Move background settings and about shit to a separate users_profiles table (should birthdate be profile specific?)
// - Create a users_stats table containing static counts for things like followers, followings, topics, posts, etc.

class User implements HasRankInterface, JsonSerializable {
    public const NAME_MIN_LENGTH =  3;               // Minimum username length
    public const NAME_MAX_LENGTH = 16;               // Maximum username length, unless your name is Flappyzor(WorldwideOnline2018through2019through2020)
    public const NAME_REGEX      = '[A-Za-z0-9-_]+'; // Username character constraint

    // Minimum amount of unique characters for passwords
    public const PASSWORD_UNIQUE = 6;

    // Password hashing algorithm
    public const PASSWORD_ALGO = PASSWORD_ARGON2ID;

    // Maximum length of profile about section
    public const PROFILE_ABOUT_MAX_LENGTH     = 60000;
    public const PROFILE_ABOUT_MAX_LENGTH_OLD = 65535; // Used for malloc's essay

    // Maximum length of forum signature
    public const FORUM_SIGNATURE_MAX_LENGTH = 2000;

    // Order constants for ::all function
    public const ORDER_ID            = 'id';
    public const ORDER_NAME          = 'name';
    public const ORDER_COUNTRY       = 'country';
    public const ORDER_CREATED       = 'registered';
    public const ORDER_ACTIVE        = 'last-online';
    public const ORDER_FORUM_TOPICS  = 'forum-topics';
    public const ORDER_FORUM_POSTS   = 'forum-posts';
    public const ORDER_FOLLOWING     = 'following';
    public const ORDER_FOLLOWERS     = 'followers';

    // Database fields
    private $user_id = -1;
    private $username = '';
    private $password = '';
    private $email = '';
    private $register_ip = '::1';
    private $last_ip = '::1';
    private $user_super = 0;
    private $user_country = 'XX';
    private $user_colour = null;
    private $user_created = null;
    private $user_active = null;
    private $user_deleted = null;
    private $display_role = 1;
    private $user_totp_key = null;
    private $user_about_content = null;
    private $user_about_parser = 0;
    private $user_signature_content = null;
    private $user_signature_parser = 0;
    private $user_birthdate = null;
    private $user_background_settings = 0;
    private $user_title = null;

    private static $localUser = null;

    private $totp = null;

    public const TABLE = 'users';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`user_id`, %1$s.`username`, %1$s.`password`, %1$s.`email`, %1$s.`user_super`, %1$s.`user_title`'
                         . ', %1$s.`user_country`, %1$s.`user_colour`, %1$s.`display_role`, %1$s.`user_totp_key`'
                         . ', %1$s.`user_about_content`, %1$s.`user_about_parser`'
                         . ', %1$s.`user_signature_content`, %1$s.`user_signature_parser`'
                         . ', %1$s.`user_birthdate`, %1$s.`user_background_settings`'
                         . ', INET6_NTOA(%1$s.`register_ip`) AS `register_ip`'
                         . ', INET6_NTOA(%1$s.`last_ip`) AS `last_ip`'
                         . ', UNIX_TIMESTAMP(%1$s.`user_created`) AS `user_created`'
                         . ', UNIX_TIMESTAMP(%1$s.`user_active`) AS `user_active`'
                         . ', UNIX_TIMESTAMP(%1$s.`user_deleted`) AS `user_deleted`';

    public function getId(): int {
        return $this->user_id < 1 ? -1 : $this->user_id;
    }

    public function getUsername(): string {
        return $this->username;
    }
    public function setUsername(string $username): self {
        $this->username = $username;
        return $this;
    }

    public function getEmailAddress(): string {
        return $this->email;
    }
    public function setEmailAddress(string $address): self {
        $this->email = mb_strtolower($address);
        return $this;
    }

    public function getRegisterRemoteAddress(): string {
        return $this->register_ip ?? '::1';
    }
    public function getLastRemoteAddress(): string {
        return $this->last_ip ?? '::1';
    }

    public function isSuper(): bool {
        return boolval($this->user_super);
    }
    public function setSuper(bool $super): self {
        $this->user_super = $super ? 1 : 0;
        return $this;
    }

    public function hasCountry(): bool {
        return $this->user_country !== 'XX';
    }
    public function getCountry(): string {
        return $this->user_country ?? 'XX';
    }
    public function setCountry(string $country): self {
        $this->user_country = strtoupper(substr($country, 0, 2));
        return $this;
    }
    public function getCountryName(): string {
        return get_country_name($this->getCountry());
    }

    private $userColour = null;
    private $realColour = null;

    public function getColour(): Colour { // Swaps role colour in if user has no personal colour
        if($this->realColour === null) {
            $this->realColour = $this->getUserColour();
            if($this->realColour->getInherit())
                $this->realColour = $this->getDisplayRole()->getColour();
        }
        return $this->realColour;
    }
    public function setColour(?Colour $colour): self {
        return $this->setColourRaw($colour === null ? null : $colour->getRaw());
    }
    public function getUserColour(): Colour { // Only ever gets the user's actual colour
        if($this->userColour === null)
            $this->userColour = new Colour($this->getColourRaw());
        return $this->userColour;
    }
    public function getColourRaw(): int {
        return $this->user_colour ?? 0x40000000;
    }
    public function setColourRaw(?int $colour): self {
        $this->user_colour = $colour;
        $this->userColour = null;
        $this->realColour = null;
        return $this;
    }

    public function getCreatedTime(): int {
        return $this->user_created === null ? -1 : $this->user_created;
    }

    public function hasBeenActive(): bool {
        return $this->user_active !== null;
    }
    public function getActiveTime(): int {
        return $this->user_active === null ? -1 : $this->user_active;
    }

    private $userRank = null;
    public function getRank(): int {
        if($this->userRank === null)
            $this->userRank = (int)DB::prepare(
                'SELECT MAX(`role_hierarchy`)'
                . ' FROM `' . DB::PREFIX . UserRole::TABLE . '`'
                . ' WHERE `role_id` IN (SELECT `role_id` FROM `' . DB::PREFIX . UserRoleRelation::TABLE . '` WHERE `user_id` = :user)'
            )->bind('user', $this->getId())->fetchColumn();
        return $this->userRank;
    }
    public function hasAuthorityOver(HasRankInterface $other): bool {
        // Don't even bother checking if we're a super user
        if($this->isSuper())
            return true;
        if($other instanceof self && $other->getId() === $this->getId())
            return true;
        return $this->getRank() > $other->getRank();
    }

    public function getDisplayRoleId(): int {
        return $this->display_role < 1 ? -1 : $this->display_role;
    }
    public function setDisplayRoleId(int $roleId): self {
        $this->display_role = $roleId < 1 ? -1 : $roleId;
        return $this;
    }
    public function getDisplayRole(): UserRole {
        return $this->getRoleRelations()[$this->getDisplayRoleId()]->getRole();
    }
    public function setDisplayRole(UserRole $role): self {
        if($this->hasRole($role))
            $this->setDisplayRoleId($role->getId());
        return $this;
    }
    public function isDisplayRole(UserRole $role): bool {
        return $this->getDisplayRoleId() === $role->getId();
    }

    public function hasTOTP(): bool {
        return !empty($this->user_totp_key);
    }
    public function getTOTP(): TOTP {
        if($this->totp === null)
            $this->totp = new TOTP($this->user_totp_key);
        return $this->totp;
    }
    public function getTOTPKey(): string {
        return $this->user_totp_key ?? '';
    }
    public function setTOTPKey(string $key): self {
        $this->totp = null;
        $this->user_totp_key = $key;
        return $this;
    }
    public function removeTOTPKey(): self {
        $this->totp = null;
        $this->user_totp_key = null;
        return $this;
    }
    public function getValidTOTPTokens(): array {
        if(!$this->hasTOTP())
            return [];
        $totp = $this->getTOTP();
        return [
            $totp->generate(time()),
            $totp->generate(time() - 30),
            $totp->generate(time() + 30),
        ];
    }

    public function hasProfileAbout(): bool {
        return !empty($this->user_about_content);
    }
    public function getProfileAboutText(): string {
        return $this->user_about_content ?? '';
    }
    public function setProfileAboutText(string $text): self {
        $this->user_about_content = empty($text) ? null : $text;
        return $this;
    }
    public function getProfileAboutParser(): int {
        return $this->hasProfileAbout() ? $this->user_about_parser : Parser::BBCODE;
    }
    public function setProfileAboutParser(int $parser): self {
        $this->user_about_parser = $parser;
        return $this;
    }
    public function getProfileAboutParsed(): string {
        if(!$this->hasProfileAbout())
            return '';
        return Parser::instance($this->getProfileAboutParser())
            ->parseText(htmlspecialchars($this->getProfileAboutText()));
    }

    public function hasForumSignature(): bool {
        return !empty($this->user_signature_content);
    }
    public function getForumSignatureText(): string {
        return $this->user_signature_content ?? '';
    }
    public function setForumSignatureText(string $text): self {
        $this->user_signature_content = empty($text) ? null : $text;
        return $this;
    }
    public function getForumSignatureParser(): int {
        return $this->hasForumSignature() ? $this->user_signature_parser : Parser::BBCODE;
    }
    public function setForumSignatureParser(int $parser): self {
        $this->user_signature_parser = $parser;
        return $this;
    }
    public function getForumSignatureParsed(): string {
        if(!$this->hasForumSignature())
            return '';
        return Parser::instance($this->getForumSignatureParser())
            ->parseText(htmlspecialchars($this->getForumSignatureText()));
    }

    // Address these through getBackgroundInfo()
    public function getBackgroundSettings(): int {
        return $this->user_background_settings;
    }
    public function setBackgroundSettings(int $settings): self {
        $this->user_background_settings = $settings;
        return $this;
    }

    public function hasTitle(): bool {
        return !empty($this->user_title);
    }
    public function getTitle(): string {
        return $this->user_title ?? '';
    }
    public function setTitle(string $title): self {
        $this->user_title = empty($title) ? null : $title;
        return $this;
    }

    public function hasBirthdate(): bool {
        return $this->user_birthdate !== null;
    }
    public function getBirthdate(): DateTime {
        return new DateTime($this->user_birthdate ?? '0000-01-01', new DateTimeZone('UTC'));
    }
    public function setBirthdate(int $year, int $month, int $day): self {
        $this->user_birthdate = $month < 1 || $day < 1 ? null : sprintf('%04d-%02d-%02d', $year, $month, $day);
        return $this;
    }
    public function hasAge(): bool {
        return $this->hasBirthdate() && intval($this->getBirthdate()->format('Y')) > 1900;
    }
    public function getAge(): int {
        if(!$this->hasAge())
            return -1;
        return intval($this->getBirthdate()->diff(new DateTime('now', new DateTimeZone('UTC')))->format('%y'));
    }

    public function profileFields(bool $filterEmpty = true): array {
        if(($userId = $this->getId()) < 1)
            return [];
        return ProfileField::user($userId, $filterEmpty);
    }

    public function bumpActivity(?string $lastRemoteAddress = null): void {
        $this->user_active = time();
        $this->last_ip = $lastRemoteAddress ?? IPAddress::remote();

        DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `user_active` = FROM_UNIXTIME(:active), `last_ip` = INET6_ATON(:address)'
            . ' WHERE `user_id` = :user'
        )   ->bind('user', $this->user_id)
            ->bind('active', $this->user_active)
            ->bind('address', $this->last_ip)
            ->execute();
    }

    // TODO: Is this the proper location/implementation for this? (no)
    private $commentPermsArray = null;
    public function commentPerms(): array {
        if($this->commentPermsArray === null)
            $this->commentPermsArray = perms_check_user_bulk(MSZ_PERMS_COMMENTS, $this->getId(), [
                'can_comment' => MSZ_PERM_COMMENTS_CREATE,
                'can_delete' => MSZ_PERM_COMMENTS_DELETE_OWN | MSZ_PERM_COMMENTS_DELETE_ANY,
                'can_delete_any' => MSZ_PERM_COMMENTS_DELETE_ANY,
                'can_pin' => MSZ_PERM_COMMENTS_PIN,
                'can_lock' => MSZ_PERM_COMMENTS_LOCK,
                'can_vote' => MSZ_PERM_COMMENTS_VOTE,
            ]);
        return $this->commentPermsArray;
    }

    private $legacyPerms = null;
    public function getLegacyPerms(): array {
        if($this->legacyPerms === null)
            $this->legacyPerms = perms_get_user($this->getId());
        return $this->legacyPerms;
    }

    /********
     * JSON *
     ********/

    public function jsonSerialize() {
        return [
            'id' => $this->getId(),
            'username' => $this->getUsername(),
            'country' => $this->getCountry(),
            'is_super' => $this->isSuper(),
            'rank' => $this->getRank(),
            'display_role' => $this->getDisplayRoleId(),
            'title' => $this->getTitle(),
            'created' => date('c', $this->getCreatedTime()),
            'last_active' => ($date = $this->getActiveTime()) < 0 ? null : date('c', $date),
            'avatar' => $this->getAvatarInfo(),
            'background' => $this->getBackgroundInfo(),
        ];
    }

    /************
     * PASSWORD *
     ************/

    public static function hashPassword(string $password): string {
        return password_hash($password, self::PASSWORD_ALGO);
    }
    public function hasPassword(): bool {
        return !empty($this->password);
    }
    public function checkPassword(string $password): bool {
        return $this->hasPassword() && password_verify($password, $this->password);
    }
    public function passwordNeedsRehash(): bool {
        return password_needs_rehash($this->password, self::PASSWORD_ALGO);
    }
    public function removePassword(): self {
        $this->password = null;
        return $this;
    }
    public function setPassword(string $password): self {
        $this->password = self::hashPassword($password);
        return $this;
    }

    /************
     * DELETING *
     ************/

    private const NUKE_TIMEOUT = 600;

    public function getDeletedTime(): int {
        return $this->user_deleted === null ? -1 : $this->user_deleted;
    }
    public function isDeleted(): bool {
        return $this->getDeletedTime() >= 0;
    }
    public function delete(): void {
        if($this->isDeleted())
            return;
        $this->user_deleted = time();
        DB::prepare('UPDATE `' . DB::PREFIX . self::TABLE . '` SET `user_deleted` = NOW() WHERE `user_id` = :user')
            ->bind('user', $this->user_id)
            ->execute();
    }
    public function restore(): void {
        if(!$this->isDeleted())
            return;
        $this->user_deleted = null;
        DB::prepare('UPDATE `' . DB::PREFIX . self::TABLE . '` SET `user_deleted` = NULL WHERE `user_id` = :user')
            ->bind('user', $this->user_id)
            ->execute();
    }
    public function canBeNuked(): bool {
        return $this->isDeleted() && time() > $this->getDeletedTime() + self::NUKE_TIMEOUT;
    }
    public function nuke(): void {
        if(!$this->canBeNuked())
            return;
        DB::prepare('DELETE FROM `' . DB::PREFIX . self::TABLE . '` WHERE `user_id` = :user')
            ->bind('user', $this->user_id)
            ->execute();
    }

    /**********
     * ASSETS *
     **********/

    private $avatarAsset = null;
    public function getAvatarInfo(): UserAvatarAsset {
        if($this->avatarAsset === null)
            $this->avatarAsset = new UserAvatarAsset($this);
        return $this->avatarAsset;
    }
    public function hasAvatar(): bool {
        return $this->getAvatarInfo()->isPresent();
    }

    private $backgroundAsset = null;
    public function getBackgroundInfo(): UserBackgroundAsset {
        if($this->backgroundAsset === null)
            $this->backgroundAsset = new UserBackgroundAsset($this);
        return $this->backgroundAsset;
    }
    public function hasBackground(): bool {
        return $this->getBackgroundInfo()->isPresent();
    }

    /*********
     * ROLES *
     *********/

    private $roleRelations = null;

    public function addRole(UserRole $role, bool $display = false): void {
        if(!$this->hasRole($role))
            $this->roleRelations[$role->getId()] = UserRoleRelation::create($this, $role);

        if($display && $this->isDisplayRole($role))
            $this->setDisplayRole($role);
    }

    public function removeRole(UserRole $role): void {
        if(!$this->hasRole($role))
            return;
        UserRoleRelation::destroy($this, $role);
        unset($this->roleRelations[$role->getId()]);

        if($this->isDisplayRole($role))
            $this->setDisplayRoleId(UserRole::DEFAULT);
    }

    public function getRoleRelations(): array {
        if($this->roleRelations === null) {
            $this->roleRelations = [];
            foreach(UserRoleRelation::byUser($this) as $rel)
                $this->roleRelations[$rel->getRoleId()] = $rel;
        }
        return $this->roleRelations;
    }

    public function getRoles(): array {
        $roles = [];
        foreach($this->getRoleRelations() as $rel)
            $roles[$rel->getRoleId()] = $rel->getRole();
        return $roles;
    }

    public function hasRole(UserRole $role): bool {
        return array_key_exists($role->getId(), $this->getRoleRelations());
    }

    /*************
     * RELATIONS *
     *************/

    private $relationCache = [];
    private $relationFollowingCount = -1;
    private $relationFollowersCount = -1;

    public function getRelation(self $other): UserRelation {
        if(isset($this->relationCache[$other->getId()]))
            return $this->relationCache[$other->getId()];
        return $this->relationCache[$other->getId()] = UserRelation::byUserAndSubject($this, $other);
    }
    public function getRelationString(self $other): string {
        if($other->getId() === $this->getId())
            return 'self';

        $from = $this->getRelation($other);
        $to   = $other->getRelation($this);

        if($from->isFollow() && $to->isFollow())
            return 'mutual';
        if($from->isFollow())
            return 'following';
        if($to->isFollow())
            return 'followed';
        return 'none';
    }
    public function getRelationTime(self $other): int {
        if($other->getId() === $this->getId())
            return -1;

        $from = $this->getRelation($other);
        $to   = $other->getRelation($this);

        return max($from->getCreationTime(), $to->getCreationTime());
    }
    public function addFollower(self $other): void {
        UserRelation::create($other, $this, UserRelation::TYPE_FOLLOW);
        unset($this->relationCache[$other->getId()]);
    }
    public function removeRelation(self $other): void {
        UserRelation::destroy($other, $this);
        unset($this->relationCache[$other->getId()]);
    }
    public function getFollowers(?Pagination $pagination = null): array {
        return UserRelation::bySubject($this, UserRelation::TYPE_FOLLOW, $pagination);
    }
    public function getFollowersCount(): int {
        if($this->relationFollowersCount < 0)
            $this->relationFollowersCount = UserRelation::countBySubject($this, UserRelation::TYPE_FOLLOW);
        return $this->relationFollowersCount;
    }
    public function getFollowing(?Pagination $pagination = null): array {
        return UserRelation::byUser($this, UserRelation::TYPE_FOLLOW, $pagination);
    }
    public function getFollowingCount(): int {
        if($this->relationFollowingCount < 0)
            $this->relationFollowingCount = UserRelation::countByUser($this, UserRelation::TYPE_FOLLOW);
        return $this->relationFollowingCount;
    }

    /***************
     * FORUM STATS *
     ***************/

    private $forumTopicCount = -1;
    private $forumPostCount = -1;

    public function getForumTopicCount(): int {
        if($this->forumTopicCount < 0)
            $this->forumTopicCount = (int)DB::prepare('SELECT COUNT(*) FROM `msz_forum_topics` WHERE `user_id` = :user AND `topic_deleted` IS NULL')
                                        ->bind('user', $this->getId())
                                        ->fetchColumn();
        return $this->forumTopicCount;
    }
    public function getForumPostCount(): int {
        if($this->forumPostCount < 0)
            $this->forumPostCount = (int)DB::prepare('SELECT COUNT(*) FROM `msz_forum_posts` WHERE `user_id` = :user AND `post_deleted` IS NULL')
                                        ->bind('user', $this->getId())
                                        ->fetchColumn();
        return $this->forumPostCount;
    }

    /************
     * WARNINGS *
     ************/

    private $activeWarning = -1;

    public function getActiveWarning(): ?UserWarning {
        if($this->activeWarning === -1)
            $this->activeWarning = UserWarning::byUserActive($this);
        return $this->activeWarning;
    }
    public function hasActiveWarning(): bool {
        return $this->getActiveWarning() !== null && !$this->getActiveWarning()->hasExpired();
    }
    public function isSilenced(): bool {
        return $this->hasActiveWarning() && $this->getActiveWarning()->isSilence();
    }
    public function isBanned(): bool {
        return $this->hasActiveWarning() && $this->getActiveWarning()->isBan();
    }
    public function getActiveWarningExpiration(): int {
        return !$this->hasActiveWarning() ? 0 : $this->getActiveWarning()->getExpirationTime();
    }
    public function isActiveWarningPermanent(): bool {
        return $this->hasActiveWarning() && $this->getActiveWarning()->isPermanent();
    }
    public function getProfileWarnings(?self $viewer): array {
        return UserWarning::byProfile($this, $viewer);
    }

    /**************
     * LOCAL USER *
     **************/

    public function setCurrent(): void {
        self::$localUser = $this;
    }
    public static function unsetCurrent(): void {
        self::$localUser = null;
    }
    public static function getCurrent(): ?self {
        return self::$localUser;
    }
    public static function hasCurrent(): bool {
        return self::$localUser !== null;
    }

    public function getClientJson(): string {
        return json_encode([
            'user_id' => $this->getId(),
            'username' => $this->getUsername(),
            'user_colour' => $this->getColour()->getRaw(),
            'perms' => $this->getLegacyPerms(),
        ]);
    }

    /**************
     * VALIDATION *
     **************/

    public static function validateUsername(string $name): string {
        if($name !== trim($name))
            return 'trim';

        $length = mb_strlen($name);
        if($length < self::NAME_MIN_LENGTH)
            return 'short';
        if($length > self::NAME_MAX_LENGTH)
            return 'long';

        if(!preg_match('#^' . self::NAME_REGEX . '$#u', $name))
            return 'invalid';

        $userId = (int)DB::prepare(
            'SELECT `user_id`'
            . ' FROM `' . DB::PREFIX . self::TABLE . '`'
            . ' WHERE LOWER(`username`) = LOWER(:username)'
        )   ->bind('username', $name)
            ->fetchColumn();
        if($userId > 0)
            return 'in-use';

        return '';
    }

    public static function usernameValidationErrorString(string $error): string {
        switch($error) {
            case 'trim':
                return 'Your username may not start or end with spaces!';
            case 'short':
                return sprintf('Your username is too short, it has to be at least %d characters!', self::NAME_MIN_LENGTH);
            case 'long':
                return sprintf("Your username is too long, it can't be longer than %d characters!", self::NAME_MAX_LENGTH);
            case 'invalid':
                return 'Your username contains invalid characters.';
            case 'in-use':
                return 'This username is already taken!';
            case '':
                return 'This username is correctly formatted!';
            default:
                return 'This username is incorrectly formatted.';
        }
    }

    public static function validateEMailAddress(string $address): string {
        if(filter_var($address, FILTER_VALIDATE_EMAIL) === false)
            return 'format';
        if(!checkdnsrr(mb_substr(mb_strstr($address, '@'), 1), 'MX'))
            return 'dns';

        $userId = (int)DB::prepare(
            'SELECT `user_id`'
            . ' FROM `' . DB::PREFIX . self::TABLE . '`'
            . ' WHERE LOWER(`email`) = LOWER(:email)'
        )   ->bind('email', $address)
            ->fetchColumn();
        if($userId > 0)
            return 'in-use';

        return '';
    }

    public static function validatePassword(string $password): string {
        if(unique_chars($password) < self::PASSWORD_UNIQUE)
            return 'weak';

        return '';
    }

    public static function validateBirthdate(int $year, int $month, int $day, int $yearRange = 100): string {
        if($year > 0) {
            if($year < date('Y') - $yearRange || $year > date('Y'))
                return 'year';
            $checkYear = $year;
        } else $checkYear = date('Y');

        if(!($day === 0 && $month === 0) && !checkdate($month, $day, $checkYear))
            return 'date';

        return '';
    }

    public static function validateProfileAbout(int $parser, string $text, bool $useOld = false): string {
        if(!Parser::isValid($parser))
            return 'parser';

        $length = strlen($text);
        if($length > ($useOld ? self::PROFILE_ABOUT_MAX_LENGTH_OLD : self::PROFILE_ABOUT_MAX_LENGTH))
            return 'long';

        return '';
    }

    public static function validateForumSignature(int $parser, string $text): string {
        if(!Parser::isValid($parser))
            return 'parser';

        $length = strlen($text);
        if($length > self::FORUM_SIGNATURE_MAX_LENGTH)
            return 'long';

        return '';
    }

    /*********************
     * CREATION + SAVING *
     *********************/

    public function save(): void {
        $save = DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `username` = :username, `email` = :email, `password` = :password'
            . ', `user_super` = :is_super, `user_country` = :country, `user_colour` = :colour, `user_title` = :title'
            . ', `display_role` = :display_role, `user_birthdate` = :birthdate, `user_totp_key` = :totp'
            . ' WHERE `user_id` = :user'
        )   ->bind('user', $this->user_id)
            ->bind('username', $this->username)
            ->bind('email', $this->email)
            ->bind('password', $this->password)
            ->bind('is_super', $this->user_super)
            ->bind('country', $this->user_country)
            ->bind('colour', $this->user_colour)
            ->bind('display_role', $this->display_role)
            ->bind('birthdate', $this->user_birthdate)
            ->bind('totp', $this->user_totp_key)
            ->bind('title', $this->user_title)
            ->execute();
    }

    public function saveProfile(): void {
        $save = DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `user_about_content` = :about_content, `user_about_parser` = :about_parser'
            . ', `user_signature_content` = :signature_content, `user_signature_parser` = :signature_parser'
            . ', `user_background_settings` = :background_settings'
            . ' WHERE `user_id` = :user'
        )   ->bind('user', $this->user_id)
            ->bind('about_content', $this->user_about_content)
            ->bind('about_parser',  $this->user_about_parser)
            ->bind('signature_content', $this->user_signature_content)
            ->bind('signature_parser',  $this->user_signature_parser)
            ->bind('background_settings', $this->user_background_settings)
            ->execute();
    }

    public static function create(
        string $username,
        string $password,
        string $email,
        string $ipAddress
    ): self {
        $createUser = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . self::TABLE . '` (`username`, `password`, `email`, `register_ip`, `last_ip`, `user_country`, `display_role`)'
            . ' VALUES (:username, :password, LOWER(:email), INET6_ATON(:register_ip), INET6_ATON(:last_ip), :user_country, 1)'
        )   ->bind('username', $username)
            ->bind('email', $email)
            ->bind('register_ip', $ipAddress)
            ->bind('last_ip', $ipAddress)
            ->bind('password', self::hashPassword($password))
            ->bind('user_country', IPAddress::country($ipAddress))
            ->executeGetId();

        if($createUser < 1)
            throw new UserCreationFailedException;

        return self::byId($createUser);
    }

    /************
     * FETCHING *
     ************/

    private static function countQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf('COUNT(*)', self::TABLE));
    }
    public static function countAll(bool $showDeleted = false): int {
        return (int)DB::prepare(
            self::countQueryBase()
            . ($showDeleted ? '' : ' WHERE `user_deleted` IS NULL')
        )->fetchColumn();
    }

    private static function memoizer() {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $userId): ?self {
        return self::memoizer()->find($userId, function() use ($userId) {
            $user = DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user_id')
                ->bind('user_id', $userId)
                ->fetchObject(self::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function byUsername(string $username): ?self {
        $username = mb_strtolower($username);
        return self::memoizer()->find(function($user) use ($username) {
            return mb_strtolower($user->getUsername()) === $username;
        }, function() use ($username) {
            $user = DB::prepare(self::byQueryBase() . ' WHERE LOWER(`username`) = :username')
                ->bind('username', $username)
                ->fetchObject(self::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function byEMailAddress(string $address): ?self {
        $address = mb_strtolower($address);
        return self::memoizer()->find(function($user) use ($address) {
            return mb_strtolower($user->getEmailAddress()) === $address;
        }, function() use ($address) {
            $user = DB::prepare(self::byQueryBase() . ' WHERE LOWER(`email`) = :email')
                ->bind('email', $address)
                ->fetchObject(self::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function byUsernameOrEMailAddress(string $usernameOrAddress): self {
        $usernameOrAddressLower = mb_strtolower($usernameOrAddress);
        return self::memoizer()->find(function($user) use ($usernameOrAddressLower) {
            return mb_strtolower($user->getUsername())     === $usernameOrAddressLower
                || mb_strtolower($user->getEmailAddress()) === $usernameOrAddressLower;
        }, function() use ($usernameOrAddressLower) {
            $user = DB::prepare(self::byQueryBase() . ' WHERE LOWER(`email`) = :email OR LOWER(`username`) = :username')
                ->bind('email', $usernameOrAddressLower)
                ->bind('username', $usernameOrAddressLower)
                ->fetchObject(self::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function byLatest(): ?self {
        return DB::prepare(self::byQueryBase() . ' WHERE `user_deleted` IS NULL ORDER BY `user_id` DESC LIMIT 1')
            ->fetchObject(self::class);
    }
    public static function findForProfile($userIdOrName): ?self {
        $userIdOrNameLower = mb_strtolower($userIdOrName);
        return self::memoizer()->find(function($user) use ($userIdOrNameLower) {
            return $user->getId() == $userIdOrNameLower || mb_strtolower($user->getUsername()) === $userIdOrNameLower;
        }, function() use ($userIdOrName) {
            $user = DB::prepare(self::byQueryBase() . ' WHERE `user_id` = :user_id OR LOWER(`username`) = LOWER(:username)')
                ->bind('user_id', (int)$userIdOrName)
                ->bind('username', (string)$userIdOrName)
                ->fetchObject(self::class);
            if(!$user)
                throw new UserNotFoundException;
            return $user;
        });
    }
    public static function byBirthdate(?DateTime $date = null): array {
        $date = $date === null ? new DateTime('now', new DateTimeZone('UTC')) : (clone $date)->setTimezone(new DateTimeZone('UTC'));
        return DB::prepare(self::byQueryBase() . ' WHERE `user_deleted` IS NULL AND `user_birthdate` LIKE :date')
            ->bind('date', $date->format('%-m-d'))
            ->fetchObjects(self::class);
    }
    public static function all(bool $showDeleted = false, ?Pagination $pagination = null): array {
        $query = self::byQueryBase();

        if(!$showDeleted)
            $query .= ' WHERE `user_deleted` IS NULL';

        $query .= ' ORDER BY `user_id` ASC';

        if($pagination !== null)
            $query .= ' LIMIT :range OFFSET :offset';

        $getObjects = DB::prepare($query);

        if($pagination !== null)
            $getObjects->bind('range', $pagination->getRange())
                ->bind('offset', $pagination->getOffset());

        return $getObjects->fetchObjects(self::class);
    }
}
