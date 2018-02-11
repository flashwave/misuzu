<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Model;
use Misuzu\Net\IP;

class User extends Model
{
    use SoftDeletes;

    private const PASSWORD_HASH_ALGO = PASSWORD_ARGON2I;

    public const USERNAME_MIN_LENGTH = 3;
    public const USERNAME_MAX_LENGTH = 16;

    protected $primaryKey = 'user_id';

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

        if (strpos($username, '  ') !== false || !preg_match('#^[A-Za-z0-9-\[\]_ ]+$#u', $username)) {
            return 'invalid';
        }

        if (strpos($username, '_') !== false && strpos($username, ' ') !== false) {
            return 'spacing';
        }

        return '';
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

    public function validatePassword(string $password): bool
    {
        if (password_needs_rehash($this->password, self::PASSWORD_HASH_ALGO)) {
            $this->password = $password;
            $this->save();
        }

        return password_verify($password, $this->password);
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
