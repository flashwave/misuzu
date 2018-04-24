<?php
namespace Misuzu\Users;

use Misuzu\Model;

/**
 * Class UserRole
 * @package Misuzu\Users
 * @property int $user_id
 * @property int $role_id
 * @property-read User $user
 * @property-read Role $role
 */
class UserRole extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
