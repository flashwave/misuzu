<?php
namespace Misuzu\Users;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Colour;
use Misuzu\Database;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

/**
 * Class User
 * @package Misuzu\Users
 * @property-read int $user_id
 * @property string $username
 * @property string $password
 * @property string $email
 * @property IPAddress $register_ip
 * @property IPAddress $last_ip
 * @property string $user_country
 * @property Carbon $user_registered
 * @property string $user_chat_key
 * @property int $display_role
 * @property string $user_website
 * @property string $user_twitter
 * @property string $user_github
 * @property string $user_skype
 * @property string $user_discord
 * @property string $user_youtube
 * @property string $user_steam
 * @property string $user_twitchtv
 * @property string $user_osu
 * @property string $user_lastfm
 * @property string $user_title
 * @property Carbon $last_seen
 * @property Carbon|null $deleted_at
 * @property-read array $sessions
 * @property-read array $roles
 * @property-read array $loginAttempts
 */
class User extends Model
{
    use SoftDeletes;

    /**
     * Define the preferred password hashing algoritm to be used to password_hash.
     */
    private const PASSWORD_HASH_ALGO = PASSWORD_ARGON2I;

    /**
     * Minimum entropy value for passwords.
     */
    public const PASSWORD_MIN_ENTROPY = 32;

    /**
     * Minimum username length.
     */
    public const USERNAME_MIN_LENGTH = 3;

    /**
     * Maximum username length, unless your name is Flappyzor(WorldwideOnline2018).
     */
    public const USERNAME_MAX_LENGTH = 16;

    /**
     * Username character constraint.
     */
    public const USERNAME_REGEX = '#^[A-Za-z0-9-_ ]+$#u';

    /**
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * Whether the display role has been validated to still be assigned to this user.
     * @var bool
     */
    private $displayRoleValidated = false;

    /**
     * Instance of the display role.
     * @var Role
     */
    private $displayRoleInstance;

    /**
     * Displayed user title.
     * @var string
     */
    private $userTitleValue;

    /**
     * Created a new user.
     * @param string         $username
     * @param string         $password
     * @param string         $email
     * @param IPAddress|null $ipAddress
     * @return User
     */
    public static function createUser(
        string $username,
        string $password,
        string $email,
        ?IPAddress $ipAddress = null
    ): User {
        $ipAddress = $ipAddress ?? IPAddress::remote();

        $user = new User;
        $user->username = $username;
        $user->password = $password;
        $user->email = $email;
        $user->register_ip = $ipAddress;
        $user->last_ip = $ipAddress;
        $user->user_country = $ipAddress->getCountryCode();
        $user->save();

        return $user;
    }

    /**
     * Tries to find a user for the login page.
     * @param string $usernameOrEmail
     * @return User|null
     */
    public static function findLogin(string $usernameOrEmail): ?User
    {
        $usernameOrEmail = strtolower($usernameOrEmail);
        return User::whereRaw("LOWER(`username`) = '{$usernameOrEmail}'")
            ->orWhere('email', $usernameOrEmail)
            ->first();
    }

    /**
     * Validates a username string.
     * @param string $username
     * @param bool   $checkInUse
     * @return string
     */
    public static function validateUsername(string $username, bool $checkInUse = false): string
    {
        $username_length = strlen($username);

        if ($username !== trim($username)) {
            return 'trim';
        }

        if ($username_length < self::USERNAME_MIN_LENGTH) {
            return 'short';
        }

        if ($username_length > self::USERNAME_MAX_LENGTH) {
            return 'long';
        }

        if (strpos($username, '  ') !== false) {
            return 'double-spaces';
        }

        if (!preg_match(self::USERNAME_REGEX, $username)) {
            return 'invalid';
        }

        if (strpos($username, '_') !== false && strpos($username, ' ') !== false) {
            return 'spacing';
        }

        if ($checkInUse && static::whereRaw("LOWER(`username`) = LOWER('{$username}')")->count() > 0) {
            return 'in-use';
        }

        return '';
    }

    /**
     * Validates an e-mail string.
     * @param string $email
     * @param bool   $checkInUse
     * @return string
     */
    public static function validateEmail(string $email, bool $checkInUse = false): string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'format';
        }

        if (!check_mx_record($email)) {
            return 'dns';
        }

        if ($checkInUse && static::whereRaw("LOWER(`email`) = LOWER('{$email}')")->count() > 0) {
            return 'in-use';
        }

        return '';
    }

    /**
     * Validates a password string.
     * @param string $password
     * @return string
     */
    public static function validatePassword(string $password): string
    {
        if (password_entropy($password) < self::PASSWORD_MIN_ENTROPY) {
            return 'weak';
        }

        return '';
    }

    /**
     * Gets the user's display role, it's probably safe to assume that this will always return a valid role.
     * @return Role|null
     */
    public function getDisplayRole(): ?Role
    {
        if ($this->displayRoleInstance === null) {
            $this->displayRoleInstance = Role::find($this->display_role);
        }

        return $this->displayRoleInstance;
    }

    /**
     * Gets the display colour.
     * @return Colour
     */
    public function getDisplayColour(): Colour
    {
        $role = $this->getDisplayRole();
        return $role === null ? Colour::none() : $role->role_colour;
    }

    /**
     * Gets the correct user title.
     * @return string
     */
    private function getUserTitlePrivate(): string
    {
        if (!empty($this->user_title)) {
            return $this->user_title;
        }

        $role = $this->getDisplayRole();

        if ($role !== null && !empty($role->role_title)) {
            return $role->role_title;
        }

        return '';
    }

    /**
     * Gets the user title (with memoization).
     * @return string
     */
    public function getUserTitle(): string
    {
        if (empty($this->userTitleValue)) {
            $this->userTitleValue = $this->getUserTitlePrivate();
        }

        return $this->userTitleValue;
    }

    /**
     * Assigns a role.
     * @param Role $role
     * @param bool $setDisplay
     */
    public function addRole(Role $role, bool $setDisplay = false): void
    {
        $relation = new UserRole;
        $relation->user_id = $this->user_id;
        $relation->role_id = $role->role_id;
        $relation->save();

        if ($setDisplay) {
            $this->display_role = $role->role_id;
        }
    }

    /**
     * Removes a role.
     * @param Role $role
     */
    public function removeRole(Role $role): void
    {
        UserRole::where('user_id', $this->user_id)
            ->where('role_id', $role->user_id)
            ->delete();
    }

    /**
     * Checks if a role is assigned.
     * @param Role $role
     * @return bool
     */
    public function hasRole(Role $role): bool
    {
        return UserRole::where('user_id', $this->user_id)
            ->where('role_id', $role->role_id)
            ->count() > 0;
    }

    /**
     * Verifies a password.
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        if (password_verify($password, $this->password) !== true) {
            return false;
        }

        if (password_needs_rehash($this->password, self::PASSWORD_HASH_ALGO)) {
            $this->password = $password;
            $this->save();
        }

        return true;
    }

    /**
     * Getter for the display_role attribute.
     * @param int|null $value
     * @return int
     */
    public function getDisplayRoleAttribute(?int $value): int
    {
        if (!$this->displayRoleValidated) {
            if ($value === null
                || UserRole::where('user_id', $this->user_id)->where('role_id', $value)->count() < 1) {
                $highestRole = Database::table('roles')
                    ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
                    ->where('user_id', $this->user_id)
                    ->orderBy('roles.role_hierarchy', 'desc')
                    ->first(['roles.role_id']);

                $value = $highestRole->role_id;
                $this->display_role = $value;
                $this->save();
            }

            $this->displayRoleValidated = true;
        }

        return $value;
    }

    /**
     * Setter for the display_role attribute.
     * @param int $value
     */
    public function setDisplayRoleAttribute(int $value): void
    {
        if (UserRole::where('user_id', $this->user_id)->where('role_id', $value)->count() > 0) {
            $this->attributes['display_role'] = $value;
        }
    }

    /**
     * @param null|string $dateTime
     * @return Carbon
     */
    public function getLastSeenAttribute(?string $dateTime): Carbon
    {
        return $dateTime === null ? Carbon::createFromTimestamp(-1) : new Carbon($dateTime);
    }

    /**
     * Getter for the register_ip attribute.
     * @param string $ipAddress
     * @return IPAddress
     */
    public function getRegisterIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    /**
     * Setter for the register_ip attribute.
     * @param IPAddress $ipAddress
     */
    public function setRegisterIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['register_ip'] = $ipAddress->getRaw();
    }

    /**
     * Getter for the last_ip attribute.
     * @param string $ipAddress
     * @return IPAddress
     */
    public function getLastIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    /**
     * Setter for the last_ip attribute.
     * @param IPAddress $ipAddress
     */
    public function setLastIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['last_ip'] = $ipAddress->getRaw();
    }

    /**
     * Setter for the password attribute.
     * @param string $password
     */
    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password'] = password_hash($password, self::PASSWORD_HASH_ALGO);
    }

    /**
     * Setter for the email attribute.
     * @param string $email
     */
    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower($email);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class, 'user_id');
    }
}
