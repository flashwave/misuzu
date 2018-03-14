<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Database;
use Misuzu\Model;
use Misuzu\Net\IP;

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
        ?string $ipAddress = null
    ): User {
        $ipAddress = $ipAddress ?? IP::remote();

        $user = new User;
        $user->username = $username;
        $user->password = $password;
        $user->email = $email;
        $user->register_ip = $ipAddress;
        $user->last_ip = $ipAddress;
        $user->user_country = get_country_code($ipAddress);
        $user->save();

        return $user;
    }

    public static function validateUsername(string $username): string
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

    public function validatePassword(string $password): bool
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

    public function getRegisterIpAttribute(string $ipAddress): string
    {
        return IP::pack($ipAddress);
    }

    public function setRegisterIpAttribute(string $ipAddress): void
    {
        $this->attributes['register_ip'] = IP::unpack($ipAddress);
    }

    public function getLastIpAttribute(string $ipAddress): string
    {
        return IP::pack($ipAddress);
    }

    public function setLastIpAttribute(string $ipAddress): void
    {
        $this->attributes['last_ip'] = IP::unpack($ipAddress);
    }

    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password'] = password_hash($password, self::PASSWORD_HASH_ALGO);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }

    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }
}
