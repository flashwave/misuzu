<?php
namespace Misuzu\Users;

use Misuzu\Model;

class UserRole extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
