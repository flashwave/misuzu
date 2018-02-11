<?php
namespace Misuzu\Users;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Model;

class User extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'user_id';

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }
}
