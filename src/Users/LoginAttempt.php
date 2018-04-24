<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\Builder;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

/**
 * Class LoginAttempt
 * @package Misuzu\Users
 * @property-read int $attempt_id
 * @property bool $was_successful
 * @property IPAddress $attempt_ip
 * @property string $attempt_country
 * @property int $user_id
 * @property string $user_agent
 * @property-read User $user
 */
class LoginAttempt extends Model
{
    /**
     * Primary table column.
     * @var string
     */
    protected $primaryKey = 'attempt_id';

    /**
     * Records a successful login attempt.
     * @param IPAddress   $ipAddress
     * @param User        $user
     * @param null|string $userAgent
     * @return LoginAttempt
     */
    public static function recordSuccess(IPAddress $ipAddress, User $user, ?string $userAgent = null): LoginAttempt
    {
        return static::recordAttempt(true, $ipAddress, $user, $userAgent);
    }

    /**
     * Records a failed login attempt.
     * @param IPAddress   $ipAddress
     * @param User|null   $user
     * @param null|string $userAgent
     * @return LoginAttempt
     */
    public static function recordFail(IPAddress $ipAddress, ?User $user = null, ?string $userAgent = null): LoginAttempt
    {
        return static::recordAttempt(false, $ipAddress, $user, $userAgent);
    }

    /**
     * Records a login attempt.
     * @param bool        $success
     * @param IPAddress   $ipAddress
     * @param User|null   $user
     * @param null|string $userAgent
     * @return LoginAttempt
     */
    public static function recordAttempt(
        bool $success,
        IPAddress $ipAddress,
        ?User $user = null,
        ?string $userAgent = null
    ): LoginAttempt {
        $attempt = new static;
        $attempt->was_successful = $success;
        $attempt->attempt_ip = $ipAddress;
        $attempt->user_agent = $userAgent ?? '';

        if ($user !== null) {
            $attempt->user_id = $user->user_id;
        }

        $attempt->save();

        return $attempt;
    }

    /**
     * Gets all login attempts from a given IP address.
     * @param IPAddress $ipAddress
     * @return Builder
     */
    public static function fromIpAddress(IPAddress $ipAddress): Builder
    {
        return static::where('attempt_ip', $ipAddress->getRaw());
    }

    /**
     * Setter for the IP address property.
     * @param IPAddress $ipAddress
     */
    public function setAttemptIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['attempt_ip'] = $ipAddress->getRaw();
        $this->attributes['attempt_country'] = $ipAddress->getCountryCode();
    }

    /**
     * Getter for the IP address property.
     * @param string $ipAddress
     * @return IPAddress
     */
    public function getAttemptIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    /**
     * Object relation definition for User.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
