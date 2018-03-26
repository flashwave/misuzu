<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Database;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

class User extends Model
{
    use SoftDeletes;

    private const PASSWORD_HASH_ALGO = PASSWORD_ARGON2I;

    public const USERNAME_MIN_LENGTH = 3;
    public const USERNAME_MAX_LENGTH = 16;
    public const USERNAME_REGEX = '#^[A-Za-z0-9-_ ]+$#u';

    protected $primaryKey = 'user_id';

    private $displayRoleValidated = false;

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

    public static function findLogin(string $usernameOrEmail): ?User
    {
        $usernameOrEmail = strtolower($usernameOrEmail);
        return User::whereRaw("LOWER(`username`) = '{$usernameOrEmail}'")
            ->orWhere('email', $usernameOrEmail)
            ->first();
    }

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

    public static function validatePassword(string $password): string
    {
        if (password_entropy($password) < 32) {
            return 'weak';
        }

        return '';
    }

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

    public function removeRole(Role $role): void
    {
        UserRole::where('user_id', $this->user_id)
            ->where('role_id', $role->user_id)
            ->delete();
    }

    public function hasRole(Role $role): bool
    {
        return UserRole::where('user_id', $this->user_id)
            ->where('role_id', $role->role_id)
            ->count() > 0;
    }

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

    public function getDisplayRoleAttribute(?int $value): int
    {
        if (!$this->displayRoleValidated) {
            if ($value === null || UserRole::where('user_id', $this->user_id)->where('role_id', $value)->count() > 0) {
                $highestRole = Database::table('roles')
                    ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
                    ->where('user_id', $this->user_id)
                    ->orderBy('roles.role_hierarchy')
                    ->first(['roles.role_id']);

                $value = $highestRole->role_id;
                $this->display_role = $value;
                $this->save();
            }

            $this->displayRoleValidated = true;
        }

        return $value;
    }

    public function setDisplayRoleAttribute(int $value): void
    {
        if (UserRole::where('user_id', $this->user_id)->where('role_id', $value)->count() > 0) {
            $this->attributes['display_role'] = $value;
        }
    }

    public function getRegisterIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    public function setRegisterIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['register_ip'] = $ipAddress->getRaw();
    }

    public function getLastIpAttribute(string $ipAddress): string
    {
        return IPAddress::fromRaw($ipAddress);
    }

    public function setLastIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['last_ip'] = $ipAddress->getRaw();
    }

    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password'] = password_hash($password, self::PASSWORD_HASH_ALGO);
    }

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower($email);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }

    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class, 'user_id');
    }
}
