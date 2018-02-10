<?php
namespace Misuzu\Users;

use Misuzu\Model;

class User extends Model
{
    protected $primaryKey = 'user_id';

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }
}
