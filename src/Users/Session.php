<?php
namespace Misuzu\Users;

use Misuzu\Model;

class Session extends Model
{
    protected $primaryKey = 'session_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
