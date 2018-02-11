<?php
namespace Misuzu\Users;

use Misuzu\Model;

class Role extends Model
{
    protected $primaryKey = 'role_id';

    public function users()
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }
}
