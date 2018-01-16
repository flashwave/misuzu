<?php
namespace Misuzu\Users;

use Misuzu\Model;

class User extends Model
{
    protected $primaryKey = 'user_id';
    public $timestamps = false;
}
