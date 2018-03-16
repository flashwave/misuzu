<?php
namespace Misuzu\Users;

use Carbon\Carbon;
use Misuzu\Model;
use Misuzu\Net\IPAddress;

class Session extends Model
{
    protected $primaryKey = 'session_id';
    protected $dates = ['expires_on'];

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

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hasExpired(): bool
    {
        return $this->expires_on->isPast();
    }

    public function getSessionIpAttribute(string $ipAddress): IPAddress
    {
        return IPAddress::fromRaw($ipAddress);
    }

    public function setSessionIpAttribute(IPAddress $ipAddress): void
    {
        $this->attributes['session_ip'] = $ipAddress->getRaw();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
