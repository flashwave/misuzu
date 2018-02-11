<?php
namespace Misuzu\Users;

use Misuzu\Model;
use Misuzu\Net\IP;

class Session extends Model
{
    protected $primaryKey = 'session_id';
    protected $dates = ['expires_on'];

    public function getSessionIpAttribute(string $ipAddress): string
    {
        return IP::pack($ipAddress);
    }

    public function setSessionIpAttribute(string $ipAddress): void
    {
        $this->attributes['session_ip'] = IP::unpack($ipAddress);
    }

    public function hasExpired(): bool
    {
        return $this->expires_on->isPast();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
