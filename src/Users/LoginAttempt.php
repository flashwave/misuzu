<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\Builder;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

class LoginAttempt extends Model
{
    protected $primaryKey = 'attempt_id';

    public static function recordSuccess(IPAddress $ipAddress, User $user): LoginAttempt
    {
        return static::recordAttempt(true, $ipAddress, $user);
    }

    public static function recordFail(IPAddress $ipAddress, ?User $user = null): LoginAttempt
    {
        return static::recordAttempt(false, $ipAddress, $user);
    }

    public static function recordAttempt(bool $success, IPAddress $ipAddress, ?User $user = null): LoginAttempt
    {
        $attempt = new static;
        $attempt->was_successful = $success;
        $attempt->attempt_ip = $ipAddress;

        if ($user !== null) {
            $attempt->user_id = $user;
        }

        $attempt->save();

        return $attempt;
    }

    public static function fromIpAddress(IPAddress $ipAddress): Builder
    {
        return static::where('attempt_ip', $ipAddress->getRaw());
    }

    public function setAttemptIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['attempt_ip'] = $ipAddress->getRaw();
        $this->attributes['attempt_country'] = $ipAddress->getCountryCode();
    }

    public function getAttemptIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    public function setUserIdAttribute(User $user): void
    {
        $this->attributes['user_id'] = $user->user_id;
    }

    public function getUserIdAttribute(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
