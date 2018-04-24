<?php
namespace Misuzu\Users;

use Carbon\Carbon;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

/**
 * Class Session
 * @package Misuzu\Users
 * @property-read int $session_id
 * @property int $user_id
 * @property string $session_key
 * @property IPAddress $session_ip
 * @property string $user_agent
 * @property Carbon $expires_on
 * @property string $session_country
 * @property-read User $user
 */
class Session extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'session_id';

    /**
     * @var array
     */
    protected $dates = ['expires_on'];

    /**
     * Creates a new session object.
     * @param User           $user
     * @param null|string    $userAgent
     * @param Carbon|null    $expires
     * @param IPAddress|null $ipAddress
     * @return Session
     * @throws \Exception
     */
    public static function createSession(
        User $user,
        ?string $userAgent = null,
        ?Carbon $expires = null,
        ?IPAddress $ipAddress = null
    ): Session {
        $ipAddress = $ipAddress ?? IPAddress::remote();
        $userAgent = $userAgent ?? 'Misuzu';
        $expires = $expires ?? Carbon::now()->addMonth();

        $session = new Session;
        $session->user_id = $user->user_id;
        $session->session_ip = $ipAddress;
        $session->user_agent = $userAgent;
        $session->expires_on = $expires;
        $session->session_key = self::generateKey();
        $session->save();

        return $session;
    }

    /**
     * Generates a random key.
     * @return string
     * @throws \Exception
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Returns if a session has expired.
     * @return bool
     */
    public function hasExpired(): bool
    {
        return $this->expires_on->isPast();
    }

    /**
     * Getter for the session_ip attribute.
     * @param string $ipAddress
     * @return IPAddress
     */
    public function getSessionIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    /**
     * Setter for the session_ip attribute.
     * @param IPAddress $ipAddress
     */
    public function setSessionIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['session_ip'] = $ipAddress->getRaw();
        $this->attributes['session_country'] = $ipAddress->getCountryCode();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
