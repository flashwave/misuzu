<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Model;
use Misuzu\Net\IP;

class User extends Model
{
    use SoftDeletes;

    private const PASSWORD_HASH_ALGO = PASSWORD_ARGON2I;

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
}
